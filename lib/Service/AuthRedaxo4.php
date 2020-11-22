<?php
/**
 * Redaxo4Embedded -- Embed Redaxo4 into NextCloud with SSO.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Redaxo4Embedded\Service;

use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\ILogger;
use OCP\ISession;
use OCP\IL10N;

class AuthRedaxo4
{
  use \OCA\Redaxo4Embedded\Traits\LoggerTrait;

  const COOKIE_RE = 'PHPSESSID|redaxo_sessid|KEY_PHPSESSID|KEY_redaxo_sessid';

  private $appName;

  private $config;

  private $session;

  private $urlGenerator;

  private $userId;
  private $password;
  private $proto;
  private $host;
  private $port;
  private $path;
  private $location;

  private $authHeaders;  //!< Authentication headers echoed back to the user
  private $authCookies;  //!< $key -> $value array of relevant cookies

  private $loginStatus;  // 0 unknown, -1 logged off, 1 logged on

  public function __construct(
    IConfig $config
    , IURLGenerator $urlGenerator
    , ISession $session
    , $userId
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->userId = $userId;
    $this->appName = Constants::APP_NAME;
    $this->config = $config;
    $this->urlGenerator = $urlGenerator;
    $this->session = $session;
    $this->logger = $logger;
    $this->l = $l10n;

    $location = $this->config->getAppValue($this->appName, 'externalLocation');
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

    $this->location = "index.php";
    $this->loginStatus = 0;

    $this->authCookies = [];
    $this->authHeaders = [];

    // could be done, in principle, if the cookie-path is set
    // accordingly, instead of storing things in the session.
    if (false) foreach ($_COOKIE as $cookie => $value) {
      if (preg_match('/'.self::COOKIE_RE.'/', $cookie)) {
        $this->authCookies[$cookie] = urlencode($value);
      }
    }

    // If we have auth-cookies stored in the session, fill the
    // authHeaders and -Cokies array with those. Will be replaced on
    // successful login. This is only for the OC internal
    // communication. The cookies for the iframe-embedded redaxo
    // web-pages will be send by the user's web-browser.
    $sessionAuth = $session->get('Redaxo\\authHeaders');
    if (is_array($sessionAuth)) {
      $this->authHeaders = $sessionAuth;
      foreach ($this->authHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*('.self::COOKIE_RE.')=([^;]+);/i', $header, $match)) {
          $this->authCookies[$match[1]] = $match[2];
        }
      }
    }

  }

  /**
   * Return the URL for use with an iframe or object tag
   */
  public function externalURL()
  {
    if (empty($this->proto) || empty($this->host)) {
      return null;
    }
    return $this->proto.'://'.$this->host.$this->port.$this->path;
  }

  /**
   * Log  into the external application.
   *
   * @param[in] $username Login name
   *
   * @param[in] $password credentials
   *
   * @return true if successful, false otherwise.
   */
  public function login($username, $password)
  {
    $this->cleanCookies();
    return false;
  }

  /**
   * Logoff from the external application.
   */
  public function logout()
  {
  }

  public function isLoggedIn()
  {
    $this->updateLoginStatus();

    return $this->loginStatus == 1;
  }

  /**
   * Ping the external application in order to extend its login
   * session.
   */
  public function refresh():bool
  {
    return $this->isLoggedIn();
  }

  public function updateLoginStatus()
  {
    if ($response === false && $this->loginStatus != 0 && count($this->authHeaders) > 0 && !$forceUpdate) {
      return;
    }

    if ($response === false) {
      $response = $this->doSendRequest($this->location);
    }

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

    $this->loginStatus = 0;
    if ($response !== false) {
      if (preg_match('/<form.+loginformular/mi', $response['content'])) {
        $this->loginStatus = -1;
      } else if (preg_match('/index.php[?]page=profile/m', $response['content'])) {
        $this->loginStatus = 1;
      }
    }
    $this->logDebug("Login Status: ".$this->loginStatus);
  }

  /**
   * Send a post request corresponding to $postData as post
   * values. Like doSendRequest but only allow if already logged in.
   */
  public function sendRequest($formPath, $postData = false)
  {
    if (!$this->isLoggedIn()) {
      return false;
    }

    return $this->doSendRequest($formPath, $postData);
  }

  /**Send a post request corresponding to $postData as post
   * values.
   */
  private function doSendRequest($formPath, $postData = false)
  {
    if (is_array($postData)) {
      $postData = http_build_query($postData, '', '&');
    }
    $method = (!$postData) ? "GET" : "POST";

    $cookies = [];
    foreach ($this->authCookies as $name => $value) {
      $cookies[] = "$name=$value";
    }
    $cookies = (count($cookies) > 0) ? "Cookie: " . join("; ", $cookies) . "\r\n" : '';

    // Construct the header with any relevant cookies
    $httpHeader = 'Content-Type: application/x-www-form-urlencoded'."\r\n"
                .'Content-Length: '.strlen($postData)."\r\n"
                .$cookies;

    /* Do not follow redirects, because we need the PHP session
     * cookie generated after the first successful login. Thus this
     * code ATM will only work when Redaxo is the only in-between
     * thingy issuing redirection headers.
     */
    $context = stream_context_create(array('http' => array(
      'method' => $method,
      'header' => $httpHeader,
      'content' => $postData,
      'follow_location' => 0,
    )));
    $url = $this->redaxoURL().$formPath;

    $logPostData = preg_replace('/rex_user_psw=[^&]*(&|$)/', 'rex_user_psw=XXXXXX$1', $postData);
    $this->logDebug("doSendRequest() to ".$url." data ".$logPostData);

    $fp = fopen($url, 'rb', false, $context);
    $result = '';
    $responseHdr = array();

    if ($fp !== false) {
      $result = stream_get_contents($fp);
      $responseHdr = $http_response_header;
      fclose($fp);
    }

    // Store and duplicate set cookies for forwarding to the users web client
    $redirect = false;
    $location = false;
    $newAuthHeaders = array();
    foreach ($responseHdr as $header) {
      if (preg_match('/^Set-Cookie:\s*('.self::COOKIE_RE.')=([^;]+);/i', $header, $match)) {
        if (true || $match[2] !== 'deleted') {
          $newAuthHeaders[] = $header;
          //$newAuthHeaders[] = preg_replace('|path=([^;]+);?|i', 'path='.\OC::$WEBROOT.'/;', $header);
          $this->authCookies[$match[1]] = $match[2];
          $this->logDebug("Auth Header: ".$header);
          $this->logDebug("Rex Cookie: ".$match[1]."=".$match[2]);
          $this->logDebug("AuthHeaders: ".print_r($newAuthHeaders, true));
        }
      } else if (preg_match('|^HTTP/1.[0-9]\s+(30[23])|', $header, $match)) {
        $redirect = true;
        $this->logDebug("Redirect status: ".$match[1]);
      } else if (preg_match('/^Location:\s*(\S+)$/', $header, $match)) {
        $location = $match[1];
        $this->logDebug("Redirect location: ".$location);
      }
    }
    if (count($newAuthHeaders) > 0) {
      $this->authHeaders = $newAuthHeaders;
      $this->session->set('Redaxo\\authHeaders', $this->authHeaders);
    }
    //$this->logDebug("Data Response: ".$result);

    if ($redirect && $location !== false) {
      // Follow the redirection
      if (substr($location, 0, 4) == 'http') {
        $this->logError("Refusing to follow absolute location header: ".$location);
        return false;
      }
      return $this->doSendRequest($location);
    }

    return empty($result)
      ? false
      : [ 'request' => $formPath,
          'responseHeaders' => $responseHdr,
          'content' => $result, ];
  }

  private function cleanCookies()
  {
    $this->authHeaders = array();
    $this->reqHeaders = array();
    foreach ($_COOKIE as $cookie => $value) {
      if (preg_match('/^(Redaxo4|DW).*/', $cookie)) {
        unset($_COOKIE[$cookie]);
      }
    }
  }

  /**
   * Parse a cookie header in order to obtain name, date of
   * expiry and path.
   *
   * @parm cookieHeader Guess what
   *
   * @return Array with name, value, expires and path fields, or
   * false if $cookie was not a Set-Cookie header.
   *
   */
  private function parseCookie($header)
  {
    $count = 0;
    $cookieString = preg_replace('/^Set-Cookie: /i', '', trim($header), -1, $count);
    if ($count != 1) {
      return false;
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
   * @param cookieHeader The raw header holding the cookie.
   *
   * @todo This probably should go into the Middleware as
   * afterController() and add the headers there.
   */
  private function addCookie($cookieHeader)
  {
    $thisCookie = $this->parseCookie($cookieHeader);
    $found = false;
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
   */
  public function emitAuthHeaders()
  {
    foreach ($this->authHeaders as $header) {
      //header($header, false);
      $this->addCookie($header);
    }
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
