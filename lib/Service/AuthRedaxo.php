<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
 * @license AGPL-3.0-or-later
 *
 * Redaxo is free software: you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * Redaxo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with Redaxo.  If not, see
 * <http://www.gnu.org/licenses/>.
 */

namespace OCA\Redaxo\Service;

use Exception;
use RuntimeException;
use Throwable;

use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\ILogger;
use OCP\ISession;
use OCP\Session\Exceptions\SessionNotAvailableException;
use OCP\IUserSession;
use OCP\IL10N;

use OCA\Redaxo\Exceptions\LoginException;
use OCA\Redaxo\Enums\LoginStatusEnum as LoginStatus;

/**
 * Handle authentication against a running redaxo instance and provide basic
 * request sending to that instance.
 */
class AuthRedaxo
{
  use \OCA\Redaxo\Traits\LoggerTrait;

  const COOKIE_RE = 'REX[0-9]+|PHPSESSID|redaxo_sessid|KEY_PHPSESSID|KEY_redaxo_sessid';
  const ON_ERROR_THROW = 'throw'; ///< Throw an exception on error
  const ON_ERROR_RETURN = 'return'; ///< Return boolean on error
  const CSRF_TOKEN_KEY = '_csrf_token';

  private $loginResponse;

  /** @var string */
  private $appName;

  /** @var string */
  private $userId;

  /** @var IConfig */
  private $config;

  /** @var ISession */
  private $session;

  /** @var IUserSession */
  private $userSession;

  /** @var ICredentialsStore */
  private $credentialsStore;

  /** @var IURLGenerator */
  private $urlGenerator;

  private $proto = null;
  private $host = null;
  private $port = null;
  private $path = null;
  private $location = null;

  private $authHeaders;  //!< Authentication headers echoed back to the user
  private $authCookies;  //!< $key -> $value array of relevant cookies

  /** @var LoginStatus */
  private LoginStatus $loginStatus;

  /** @var string */
  private $errorReporting;

  /** @var bool */
  private $enableSSLVerify;

  /** @var int */
  private $loginTimeStamp;

  /** @var string */
  private $csrfToken;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IConfig $config,
    ISession $session,
    IUserSession $userSession,
    ICredentialsStore $credentialsStore,
    IURLGenerator $urlGenerator,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->appName = $appName;
    $this->config = $config;
    $this->session = $session;
    $this->userSession = $userSession;
    $this->credentialsStore = $credentialsStore;
    $this->urlGenerator = $urlGenerator;
    $this->logger = $logger;
    $this->l = $l10n;

    $this->csrfToken = null;

    $this->errorReporting = self::ON_ERROR_RETURN;

    $this->enableSSLVerify = $this->config->getAppValue('enableSSLVerfiy', true);

    $this->reloginDelay = $this->config->getAppValue('reloginDelay', 5);

    $location = $this->config->getAppValue($this->appName, 'externalLocation');

    if (!empty($location)) {

      if ($location[0] == '/') {
        $url = $this->urlGenerator->getAbsoluteURL($location);
      } else {
        $url = $location;
      }

      $urlParts = parse_url($url);

      $this->proto = $urlParts['scheme'];
      $this->host  = $urlParts['host'];
      $this->port  = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
      $this->path  = $urlParts['path'];
    }

    $this->location = "index.php";

    if (!empty($this->userSession->getUser())) {
      $this->userId = $this->userSession->getUser()->getUID();
    } else {
      $this->userId = null;
    }

    $this->restoreLoginStatus(); // initialize and optionally restore from session data.
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /**
   * Return the name of the app.
   *
   * @return string
   */
  public function getAppName():string
  {
    return $this->appName;
  }

  /**
   * Modify how errors are handled.
   *
   * @param null|string $how One of self::ON_ERROR_THROW or
   * self::ON_ERROR_RETURN or null (just return the current
   * reporting).
   *
   * @return string Currently active error handling policy.
   */
  public function errorReporting($how = null)
  {
    $reporting = $this->errorReporting;
    switch ($how) {
      case null:
        break;
      case self::ON_ERROR_THROW:
      case self::ON_ERROR_RETURN:
        $this->errorReporting = $how;
        break;
      default:
        throw new Exception('Unknown error-reporting method: ' . $how);
    }
    return $reporting;
  }

  /**
   * @param null|string $msg
   *
   * @param null|Throwable $throwable
   *
   * @param mixed $result What to return.
   *
   * @return mixed
   */
  public function handleError(?string $msg, Throwable $throwable = null, mixed $result = null):mixed
  {
    switch ($this->errorReporting) {
      case self::ON_ERROR_THROW:
        if (!empty($throwable)) {
          throw empty($msg) ? $throwable : new Exception($msg, $throwable->getCode(), $throwable);
        } else {
          throw new Exception($msg);
        }
      case self::ON_ERROR_RETURN:
        if (!empty($throwable)) {
          $this->logException($throwable, $msg);
        } else {
          $this->logError($msg);
        }
        return $result;
      default:
        throw new Exception("Invalid error handling method: " . $this->errorReporting);
    }
    return $result;
  }

  /**
   * Return the URL for use with an iframe or object tag.
   *
   * @param null|string $url
   *
   * @return string
   */
  public function externalURL(?string $url = null):string
  {
    if (!empty($url)) {
      if ($url[0] == '/') {
        $url = $this->urlGenerator->getAbsoluteURL($url);
      }

      $urlParts = parse_url($url);

      $this->proto = $urlParts['scheme'];
      $this->host  = $urlParts['host'];
      $this->port  = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
      $this->path  = $urlParts['path'];
    }

    if (empty($this->proto) || empty($this->host)) {
      return null;
    }
    return $this->proto.'://'.$this->host.$this->port.$this->path;
  }

  /**
   * Try to obtain login-credentials from Nextcloud credentials store.
   *
   * @return null|array
   * ```
   * [
   *   'userId' => USER_ID,
   *   'password' => PASSWORD,
   * ]
   * ```
   */
  private function loginCredentials():?array
  {
    try {
      $credentials = $this->credentialsStore->getLoginCredentials();
      return [
        'userId' => $credentials->getUID(),
        'password' => $credentials->getPassword(),
      ];
    } catch (\Throwable $t) {
      return $this->handleError("Unable to obtain login-credentials", $t);
    }
  }

  /**
   * Log  into the external application.
   *
   * @param string $userName Login name.
   *
   * @param string $password User credentials.
   *
   * @return true if successful, false otherwise.
   */
  public function login(string $userName, string $password):bool
  {
    $this->updateLoginStatus();

    if ($this->isLoggedIn()) {
      $this->logout();
    } else {
      $this->cleanCookies();
    }

    $response = $this->sendRequest(
      $this->location,
      [
        'javascript' => 0,
        'rex_user_login' => $userName,
        'rex_user_psw' => $password,
      ]);

    $this->updateLoginStatus($response, true);

    return $this->loginStatus->equals(LoginStatus::LOGGED_IN());
  }

  /**
   * Ensure we are logged in.
   *
   * @param bool $forceUpdate
   *
   * @return bool true on success, false on error
   *
   * @throws LoginException Thrown if error reporting is set to exceptions.
   */
  public function ensureLoggedIn(bool $forceUpdate = false):bool
  {
    $this->updateLoginStatus(null, $forceUpdate);
    if (!$this->isLoggedIn()) {
      $credentials = $this->loginCredentials();
      $userName = $credentials['userId'];
      $password = $credentials['password'];
      if (!$this->login($userName, $password)) {
        $e = new LoginException(
          $this->l->t('Unable to log into Redaxo backend') . ' ' /* . $this->loginResponse */, 0, null,
          $userName, $this->loginStatus
        );
        return $this->handleError(null, $e, result: false);
      }
      $this->persistLoginStatus();
      $this->emitAuthHeaders(); // send cookies
    }
    return true;
  }

  /**
   * Persist authentication status and headers to the session.
   *
   * @return void
   */
  public function persistLoginStatus():void
  {
    if ($this->session->isClosed()) {
      $this->logWarn('Session is already closed, unable to persist login credentials.');
      return;
    }
    try {
      $this->session->set($this->appName, [
        'authHeaders' => $this->authHeaders,
        'loginStatus' => (string)$this->loginStatus,
        'loginTimeStamp' => time(),
      ]);
    } catch (SessionNotAvailableException $e) {
      // The Nextcloud log-reader does not handle custom messages :(
      $this->logException(new RuntimeException('Unable to persist login credentials to the session, session is already closed.', $e->getCode(), $e));
    }
  }

  /**
   * Restores the authentication status and headers from the session.
   *
   * @return void
   */
  private function restoreLoginStatus():void
  {
    $this->cleanCookies();
    $this->loginStatus = LoginStatus::UNKNOWN();
    $sessionData = $this->session->get($this->appName);
    if (!empty($sessionData)) {
      $this->logDebug('SESSION DATA: '.print_r($sessionData, true));
      if (!LoginStatus::isValid($sessionData['loginStatus'])) {
        $this->logError('Unable to load login status from session data');
        return;
      }
      $this->loginTimeStamp = (int)$sessionData['loginTimeStamp'];
      $this->loginStatus = LoginStatus::from($sessionData['loginStatus']);
      $this->authHeaders = $sessionData['authHeaders'];
      foreach ($this->authHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*('.self::COOKIE_RE.')=([^;]+);/i', $header, $match)) {
          $this->authCookies[$match[1]] = $match[2];
        }
      }
    }
  }

  /**
   * Logoff from the external application.
   *
   * @return bool
   */
  public function logout():bool
  {
    $response = $this->sendRequest($this->location.'?rex_logout=1');
    $this->updateLoginStatus($response);
    $this->cleanCookies();
    return $this->loginStatus->equals(LoginStatus::LOGGED_OUT());
  }

  /**
   * Return true if the current login status is "logged in".
   *
   * @param bool $forceUpdate
   *
   * @return bool
   */
  public function isLoggedIn(bool $forceUpdate = false):bool
  {
    $this->updateLoginStatus(null, $forceUpdate);

    return $this->loginStatus->equals(LoginStatus::LOGGED_IN());
  }

  /**
   * Return the current login status.
   *
   * @return LoginStatus
   */
  public function loginStatus()
  {
    $this->updateLoginStatus();

    return $this->loginStatus;
  }

  /**
   * Ping the external application in order to extend its login
   * session, but only if we are logged in. This is just to prevent
   * session starvation while the Nextcloud-app is open.
   *
   * @return bool
   */
  public function refresh():bool
  {
    if ($this->loginStatus->equals(LoginStatus::LOGGED_IN())) {
      $this->logDebug('Refreshing login for user '.$this->userId);
      return $this->isLoggedIn(true);
    }
    $this->logDebug('Not refreshing, user '.$this->loginCredentials()['userId'].' not logged in');
    return false;
  }

  /**
   * Update the internal login status.
   *
   * @param null|array $response
   *
   * @param bool $forceUpdate
   *
   * @return void
   */
  private function updateLoginStatus(?array $response = null, bool $forceUpdate = false):void
  {
    if ($response === null
        && !$this->loginStatus->equals(LoginStatus::UNKNOWN())
        && count($this->authHeaders) > 0
        && (time() - $this->loginTimeStamp <= $this->reloginDelay)
        && !$forceUpdate) {
      return;
    }

    if ($response === null) {
      $response = $this->sendRequest($this->location);
    }

    // $this->logInfo('RESPONSE ' . print_r($response, true));

    //LOGGED IN:
    //<div id="rex-navi-logout">
    //  <ul class="rex-logout">
    //    <li class="rex-navi-first"><span>Angemeldet als CafevAdmin</span></li>
    //    <li><a href="index.php?page=profile">Mein Profil</a></li>
    //    <li><a href="index.php?rex_logout=1" accesskey="l" title="abmelden [l]">abmelden</a></li>
    //  </ul>
    //</div>
    //
    // LOGGED OFF:
    //<div id="rex-navi-logout"><p class="rex-logout">nicht angemeldet</p></div>

    $this->loginStatus = LoginStatus::UNKNOWN();
    if ($response !== false) {
      //$this->logDebug(print_r($response['content'], true));
      if (preg_match('/<form.+rex-form-login/mi', $response['content'])) {
        $this->loginResponse = $response['content'];
        $this->loginStatus = LoginStatus::LOGGED_OUT();
      } elseif (preg_match('/index.php[?]page=profile/m', $response['content'])) {
        $this->loginStatus = LoginStatus::LOGGED_IN();
      }
    } else {
      $this->logInfo("Empty response from login-form");
    }
  }

  /**
   * Send a GET or POST request corresponding to $postData as post
   * values. GET is used if $postData is not an array.
   *
   * @param string $formPath
   *
   * @param null|array $postData
   *
   * @return null|array
   * ```
   * [
   *   'request' => REQUEST_URI,
   *   'responseHeaders' => RESPONSE_HEADERS,
   *   'content' => RESPONSE_BODY,
   * ]
   */
  public function sendRequest(string $formPath, ?array $postData = null):?array
  {
    if (is_array($postData)) {
      if (!empty($this->csrfToken)) {
        $postData[self::CSRF_TOKEN_KEY] = $this->csrfToken;
      }
      $postData = http_build_query($postData, '', '&');
    }
    $method = (!$postData) ? "GET" : "POST";

    $cookies = [];
    foreach ($this->authCookies as $name => $value) {
      $cookies[] = "$name=$value";
    }
    $cookies = (count($cookies) > 0) ? "Cookie: " . join("; ", $cookies) . "\r\n" : '';

    // Construct the header with any relevant cookies
    $httpHeader = $cookies;
    if ($method == 'POST') {
      $httpHeader .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
        . 'Content-Length: ' . strlen($postData) . "\r\n";
    }

    // $httpHeader .= 'Accept-Encoding: gzip, deflate, br' . "\r\n"; // gzip seems to be necessary

    /* Do not follow redirects, because we need the PHP session
     * cookie generated after the first successful login. Thus this
     * code ATM will only work when Redaxo is the only in-between
     * thingy issuing redirection headers.
     */
    $context = stream_context_create([
      'http' => [
        'method' => $method,
        'header' => $httpHeader,
        'content' => $postData,
        'follow_location' => 0,
      ],
      'ssl' => [
        'verify_peer' => $this->enableSSLVerify,
        'verify_peer_name' => $this->enableSSLVerify,
      ],
    ]);
    if (!empty($formPath) && $formPath[0] != '/') {
      $formPath = '/'.$formPath;
    }
    $url = $this->externalURL() . $formPath;

    $logPostData = preg_replace('/rex_user_psw=[^&]*(&|$)/', 'rex_user_psw=XXXXXX$1', $postData);
    $this->logDebug("... to ".$url." data ".$logPostData);

    $fp = fopen($url, 'rb', false, $context);
    $result = '';
    $responseHdr = [];

    if ($fp !== false) {
      $result = stream_get_contents($fp);
      $responseHdr = $http_response_header;
      fclose($fp);
    } else {
      $error = error_get_last();
      $headers = $http_response_header;
      return $this->handleError(
        "URL fopen to $url failed: "
        . print_r($error, true)
        . $headers[0]
      );
    }

    // Store and duplicate set cookies for forwarding to the users web client
    $redirect = false;
    $location = false;
    $newAuthHeaders = [];
    $newAuthCookies = [];
    $this->logDebug('HEADERS ' . print_r($responseHdr, true));
    foreach ($responseHdr as $header) {
      // only the last cookie counts.
      if (preg_match('/^Set-Cookie:\s*('.self::COOKIE_RE.')=([^;]+);/i', $header, $match)) {
        if (true || $match[2] !== 'deleted') {
          $newAuthHeaders = [ $header ];
          $newAuthCookies[$match[1]] = $match[2];
          $this->logDebug("Auth Header: ".$header);
          $this->logDebug("Rex Cookie: ".$match[1]."=".$match[2]);
          $this->logDebug("AuthHeaders: ".print_r($newAuthHeaders, true));
        }
      } elseif (preg_match('|^HTTP/1.[0-9]\s+(30[23])|', $header, $match)) {
        $redirect = true;
        $this->logDebug("Redirect status: ".$match[1]);
      } elseif (preg_match('/^Location:\s*(\S+)$/', $header, $match)) {
        $location = $match[1];
        $this->logDebug("Redirect location: ".$location);
      }
    }
    if (count($newAuthHeaders) > 0) {
      $this->authHeaders = $newAuthHeaders;
      $this->authCookies = $newAuthCookies;
    }
    //$this->logDebug("Data Response: ".$result);

    if ($redirect && $location !== false) {
      // Follow the redirection
      if (substr($location, 0, 4) == 'http') {
        return $this->handleError("Refusing to follow absolute location header: ".$location);
      }
      return $this->sendRequest($location);
    }

    if (empty($result)) {
      return $this->handleError("Empty result");
    }

    // update the CSRF token if there is one
    // <input type="hidden" name="_csrf_token" value="A5AJ7s6T71vhVSsoD5sJxJYtD7D0Sb_XenaBncRH200"/>

    $matches = null;
    if (preg_match('@<input[^/]+name="' . self::CSRF_TOKEN_KEY . '"[^/]+value="([^"]+)"[^/]*/>@i', $result, $matches) ||
        preg_match('@<input[^/]+value="([^"]+)"[^/]+name="' . self::CSRF_TOKEN_KEY . '"[^/]*/>@i', $result, $matches)) {
      $this->csrfToken = $matches[1];
    }

    return [
      'request' => $formPath,
      'responseHeaders' => $responseHdr,
      'content' => $result,
    ];
  }

  /**
   * Cleanout all cookies safe the PHPSESSID which is needed for the CSRF
   * token check.
   *
   * @return void
   */
  private function cleanCookies():void
  {
    $this->authHeaders = array_filter($this->authHeaders ?? [], fn($value) => str_contains($value, 'PHPSESSID'));
    $this->authCookies = array_filter($this->authCookies ?? [], fn($value, $key) => $key == 'PHPSESSID', ARRAY_FILTER_USE_BOTH);
  }

  /**
   * Parse a cookie header in order to obtain name, date of
   * expiry and path.
   *
   * @param string $header Guess what.
   *
   * @return null|array Array with name, value, expires and path fields, or
   * false if $cookie was not a Set-Cookie header.
   */
  private function parseCookie(string $header):?array
  {
    $count = 0;
    $cookieString = preg_replace('/^Set-Cookie: /i', '', trim($header), -1, $count);
    if ($count != 1) {
      return null;
    }
    $cookie = [];
    $cookieValues = explode(';', $cookieString);
    foreach ($cookieValues as $field) {
      $cookieInfo = explode('=', $field);
      $cookie[trim($cookieInfo[0])] =
        count($cookieInfo) == 2 ? trim($cookieInfo[1]) : true;
    }
    ksort($cookie);

    return $cookie;
  }

  /**
   * Normally, we do NOT want to replace cookies, we need two
   * paths: one for the RC directory, one for the OC directory
   * path. However: NGINX (a web-server software) on some
   * systems has a header limit of 4k, which is not much. At
   * least, if one tries to embed several web-applications into
   * the cloud by the same techniques which are executed here.
   *
   * This function tries to reduce the header size by replacing
   * cookies with the same name and path, but adding a new
   * cookie if name or path differs.
   *
   * @param string $cookieHeader The raw header holding the cookie.
   *
   * @todo This probably should go into the Middleware as
   * afterController() and add the headers there.
   *
   * @return void
   */
  private function addCookie(string $cookieHeader):void
  {
    $thisCookie = $this->parseCookie($cookieHeader);
    foreach (headers_list() as $header) {
      $cookie = $this->parseCookie($header);
      if ($cookie === $thisCookie) {
        return;
      }
    }
    $this->logDebug("Emitting cookie ".$cookieHeader);
    header($cookieHeader, false);
  }

  /**
   * Send authentication headers previously aquired
   *
   * @return void
   */
  public function emitAuthHeaders():void
  {
    foreach ($this->authHeaders as $header) {
      //header($header, false);
      $this->addCookie($header);
    }
  }
}
