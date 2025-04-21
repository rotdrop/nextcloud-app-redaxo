<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020-2025
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

use DOMDocument;
use DOMXPath;
use Exception;
use RuntimeException;
use Throwable;

use OCP\Authentication\LoginCredentials\IStore as ICredentialsStore;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface as ILogger;
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
  use \OCA\Redaxo\Toolkit\Traits\LoggerTrait;

  const COOKIE_RE = 'REX[0-9]+|PHPSESSID|redaxo_sessid|KEY_PHPSESSID|KEY_redaxo_sessid';
  const ON_ERROR_THROW = 'throw'; ///< Throw an exception on error
  const ON_ERROR_RETURN = 'return'; ///< Return boolean on error
  const CSRF_TOKEN_KEY = '_csrf_token';

  const LOGIN_CSRF_KEY = 'login';

  private $loginResponse;

  /** @var string */
  private $userId;

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

  /** @var array */
  private $csrfTokens;

  /** @var int */
  private $reloginDelay;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private string $appName,
    private IConfig $config,
    private ISession $session,
    private IUserSession $userSession,
    private ICredentialsStore $credentialsStore,
    private IURLGenerator $urlGenerator,
    protected ILogger $logger,
    private IL10N $l,
  ) {
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

    $this->csrfTokens = [];

    $this->restoreLoginStatus(); // initialize and optionally restore from session data.
    $this->restoreCSRFToken();
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
  public function handleError(?string $msg, ?Throwable $throwable = null, mixed $result = null):mixed
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
  public function externalURL(?string $url = null):?string
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
      ],
      csrfKey: self::LOGIN_CSRF_KEY,
    );

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
   * Stash the CSRF token away in the session data if possible.
   *
   * @return void
   */
  public function persistCSRFTokens():void
  {
    $sessionKey = $this->appName . self::CSRF_TOKEN_KEY;
    $sessionCSRFs = $this->session->get($sessionKey) ?? [];
    asort($sessionCSRFs);
    if ($sessionCSRFs != $this->csrfTokens) {
      try {
        $this->session->set($sessionKey, $this->csrfTokens);
      } catch (SessionNotAvailableException $e) {
        // The Nextcloud log-reader does not handle custom messages :(
        $this->logException(new RuntimeException('Session is already closed, unable to persist CSRF tokens.', $e->getCode(), $e));
      }
    }
  }

  /**
   * Restore the CSRF token from the session if possible.
   *
   * @return void
   */
  public function restoreCSRFToken():void
  {
    if (empty($this->csrfTokens)) {
      $csrfTokens = $this->session->get($this->appName . self::CSRF_TOKEN_KEY);
      if (!empty($csrfTokens) && is_array($csrfTokens)) {
        $this->csrfTokens = $csrfTokens;
      }
    }
  }

  /**
   * Persist authentication status and headers to the session.
   *
   * @return void
   */
  public function persistLoginStatus():void
  {
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
    if ($response !== false && !empty($response['content'])) {
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
   * Send a GET or POST request corresponding to $postData as post values. GET
   * is used if $postData is not an array. This "public" function handles
   * resend on CSRF errors.
   *
   * @param string $formPath
   *
   * @param null|array $postData
   *
   * @param null|string $csrfKey
   *
   * @return null|array
   * ```
   * [
   *   'request' => REQUEST_URI,
   *   'responseHeaders' => RESPONSE_HEADERS,
   *   'content' => RESPONSE_BODY,
   * ]
   */
  public function sendRequest(string $formPath, ?array $postData = null, string $csrfKey = self::LOGIN_CSRF_KEY):?array
  {
    $result = $this->doSendRequest($formPath, $postData, $csrfKey);
    if (!empty($result) && $this->isCSRFMismatch($result)) {
      $result = $this->doSendRequest($formPath, $postData, $csrfKey);
      if (!empty($result) && $this->isCSRFMismatch($result)) {
        $this->logError('CSRF STILL A PROBLEM');
      }
    }
    return $result;
  }

  /**
   * Send a GET or POST request corresponding to $postData as post
   * values. GET is used if $postData is not an array.
   *
   * @param string $formPath
   *
   * @param null|array $postData
   *
   * @param null|string $csrfKey
   *
   * @return null|array
   * ```
   * [
   *   'request' => REQUEST_URI,
   *   'responseHeaders' => RESPONSE_HEADERS,
   *   'content' => RESPONSE_BODY,
   * ]
   */
  private function doSendRequest(string $formPath, ?array $postData, string $csrfKey):?array
  {
    $csrfToken = $this->csrfTokens[$csrfKey] ?? null;
    $this->logDebug('CSRF ' . $csrfKey . ' -> ' . $csrfToken);
    if (is_array($postData)) {
      if (!empty($csrfToken)) {
        $postData[self::CSRF_TOKEN_KEY] = $csrfToken;
      }
      $postData = http_build_query($postData, '', '&');
      $method = 'POST';
    } else {
      $method = 'GET';
    }

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

    if (!empty($csrfToken)) {
      $join = empty(parse_url($url, PHP_URL_QUERY)) ? '?' : '&';
      $url .= $join . self::CSRF_TOKEN_KEY . '=' . $csrfToken;
    }

    $logPostData = preg_replace('/rex_user_psw=[^&]*(&|$)/', 'rex_user_psw=XXXXXX$1', $postData);
    $this->logDebug("... to " . $url . " data " . $logPostData);

    $fp = fopen($url, 'rb', false, $context);
    $result = '';
    $responseHdr = [];

    if ($fp !== false) {
      $result = stream_get_contents($fp);
      $responseHdr = $http_response_header;
      fclose($fp);
    } else {
      $error = error_get_last();
      $headers = $http_response_header ?? [];
      return $this->handleError(
        "URL fopen to $url failed: "
        . print_r($error, true)
        . print_r($headers, true)
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
      } elseif (preg_match('|^HTTP/1.[0-9]\s+(30[123])|', $header, $match)) {
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

    $document = new DOMDocument();
    $document->loadHTML($result, LIBXML_NOERROR);

    $this->updateCSRFTokens($document);

    return [
      'request' => $formPath,
      'responseHeaders' => $responseHdr,
      'content' => $result,
      'document' => $document,
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

  /**
   * Check whether the requested CSRF token is available.
   *
   * @param string $key
   *
   * @return bool
   */
  public function hasCSRFToken(string $key):bool
  {
    return !empty($this->csrfTokens[$key]);
  }

  /**
   * Update the CSRF tokens needed to place successful API calls to Redaxo 5.
   *
   * @param DOMDocument $document
   *
   * @return void
   */
  public function updateCSRFTokens(DOMDocument $document):void
  {
    $xPath = new DOMXPath($document);

    // extract the login-page CSRF
    $loginCSRF = $xPath->query("//form[@id = 'rex-form-login']/input[@name = '" . self::CSRF_TOKEN_KEY . "']");
    if (count($loginCSRF) > 0) {
      /** @var DOMElement $loginCSRF */
      $loginCSRF = $loginCSRF->item(0);
      $this->csrfTokens[self::LOGIN_CSRF_KEY] = $loginCSRF->getAttribute('value');
      asort($this->csrfTokens);
      $this->logDebug('CSRF TOKENS: ' . print_r($this->csrfTokens, true));
      return;
    }

    // extract CSRFs from hidden input elements
    $actionCSRF = $xPath->query("//tr[contains(@class, 'mark')]/td[contains(@class, 'rex-table-action')]");
    if (count($actionCSRF) > 0) {
      /** @var DOMElement $actionData */
      foreach ($actionCSRF as $actionData) {
        foreach ($actionData->getElementsByTagName('input') as $input) {
          /** @var DOMElement $input */
          switch ($input->getAttribute('name')) {
            case 'rex-api-call':
              $apiCall = $input->getAttribute('value');
              break;
            case self::CSRF_TOKEN_KEY:
              $csrfToken = $input->getAttribute('value');
              break;
            default:
              break;
          }
        }
        if (!empty($apiCall) && !empty($csrfToken)) {
          $this->csrfTokens[$apiCall] = $csrfToken;
        }
        unset($apiCall);
        unset($csrfToken);
      }
    }

    // Another variant: inline on-click handle
    // <button class="btn btn-send rex-form-aligned" type="submit" name="article_move" value="1" data-confirm="Artikel verschieben?" onclick="$(this.form).append('<input type=&quot;hidden&quot; name=&quot;rex-api-call&quot; value=&quot;article_move&quot;/><input type=&quot;hidden&quot; name=&quot;_csrf_token&quot; value=&quot;wOJVwI6tYGZk4bf-LSQNfXC1b5DK-Iqny-KSFK0mHMM&quot;/>')">Artikel verschieben</button>

    $onClickCSRF = $xPath->query("//button[@onclick]");
    /** @var DOMElement $button */
    foreach ($onClickCSRF as $button) {
      $onClickCode = html_entity_decode($button->getAttribute('onclick'));
      $matches = [];
      if (preg_match('@name="rex-api-call".*value="([^"]+)".*name="_csrf_token".*value="([^"]+)"@', $onClickCode, $matches)) {
        $this->csrfTokens[$matches[1]] = $matches[2];
      }
    }

    // https://dev4.home.claus-justus-heine.de/redaxo/index.php?page=structure&category_id=2&article_id=2&clang=1&category-id=15&catstart=0&rex-api-call=category_delete&_csrf_token=b9WbLV4X3Bntl4DJPcXh9zVReVCWFGZbSOdKWlxXztk
    // extract CSRFs from action URLs
    $urlCSRF = $xPath->query("//a[contains(@href, 'rex-api-call')]");
    /** @var DOMElement $csrfAnchor */
    foreach ($urlCSRF as $csrfAnchor) {
      $href = $csrfAnchor->getAttribute('href');
      $query = [];
      parse_str(parse_url($href, PHP_URL_QUERY), $query);
      if (!empty($query['rex-api-call']) && !empty($query[self::CSRF_TOKEN_KEY])) {
        $this->csrfTokens[$query['rex-api-call']] = $query[self::CSRF_TOKEN_KEY];
      }
    }
    asort($this->csrfTokens);

    $this->logDebug('CSRF TOKENS: ' . print_r($this->csrfTokens, true));
  }

  /**
   * Check for CSRF mismatch. In this case the request has to be repeated with
   * the updated token.
   *
   * @param array $requestResult The result returned from sendRequest().
   *
   * @return bool
   */
  public function isCSRFMismatch(array $requestResult):bool
  {
    $document = $requestResult['document'];
    $xPath = new DOMXPath($document);
    $alerts = $xPath->query("//div[contains(@class, 'alert')]");
    /** @var DOMElement $alert */
    foreach ($alerts as $alert) {
      $text = $alert->textContent;
      if (stripos($text, 'csrf') !== false) {
        $this->logError('CSRF MISMATCH');
        return true;
      }
    }
    return false;
  }
}
