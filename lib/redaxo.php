<?php

/**Main driver module for this app.
 *
 * @author Claus-Justus Heine
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**Redaxo namespace to prevent name-collisions.
 */
namespace Redaxo 
{

  class App
  {
    const APP_NAME = 'redaxo';

    const COOKIE_RE = 'PHPSESSID';
    private $user;
    private $password;
    private $proto;
    private $host;
    private $port;
    private $path;
    private $location;

    private $cookies;      //!< General cookies
    private $authHeaders;  //!< Authentication headers echoed back to the user
    private $authCookies;  //!< $key -> $value array of relevant cookies

    private $loginStatus;  // 0 unknown, -1 logged off, 1 logged on
      
    public function __construct($location)
    {
      $url = Util::composeURL($location);

      $urlParts = parse_url($url);
      $this->proto = $urlParts['scheme'];
      $this->host  = $urlParts['host'];
      $this->port  = isset($urlParts['port']) ? ':'.$urlParts['port'] : '';
      $this->path  = $urlParts['path'];

      $this->location = "index.php";
      $this->loginStatus = 0;

      $this->cookies = array();
      $this->authCookies = array();
      $this->authHeaders = array();

      // If we have cookies with AuthData, then store them in authHeaders
      foreach ($_COOKIE as $cookie => $value) {
        if (preg_match('/'.self::COOKIE_RE.'/', $cookie)) {
          $this->authCookies[$cookie] = $value;
        } else {
          $this->cookies[$cookie] = $value;
        }
      }

      // If we have auth-cookies stored in the session, fill the
      // authHeaders array with those. Will be replaced on successful
      // login.
      $sessionAuth = \OC::$session->get('Redaxo\\authHeaders');
      if (is_array($sessionAuth)) {
        $this->authHeaders = $sessionAuth;
      }
    }

    /**Return the URL for use with an iframe or object tag
     */
    public function redaxoURL($articleId = false, $editMode = false)
    {
      return $this->proto.'://'.$this->host.$this->port.$this->path;
    }

    private function cleanCookies()
    {
      $this->authHeaders = array();
      $this->authCookies = array();
      foreach ($_COOKIE as $cookie => $value) {
        if (preg_match('/'.self::COOKIE_RE.'/', $cookie)) {
          unset($_COOKIE[$cookie]);
        }
      }
    }

    private function updateLoginStatus($response = false, $forceUpdate = false)
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
        if (preg_match('/<form.+loginformular/mi', $response->getContents())) {
          $this->loginStatus = -1;
        } else if (preg_match('/index.php[?]page=profile/m', $response->getContents())) {
          $this->loginStatus = 1;
        }
      }
      \OCP\Util::writeLog(self::APP_NAME, "Login Status: ".$this->loginStatus, \OC_LOG::DEBUG);
    }

    public function isLoggedIn() 
    {
      $this->updateLoginStatus();
      
      return $this->loginStatus == 1;
    }
    
    public function logout()
    {
      $response = $this->doSendRequest($this->location.'?rex_logout=1');
      $this->updateLoginStatus($response);
      return $this->loginStatus == -1;
    }

    public function login($user, $password)
    {
      $this->updateLoginStatus();
      
      if ($this->isLoggedIn()) {
        $this->logout();
      }

      $response = $this->doSendRequest($this->location,
                                     array('javascript' => 1,
                                           'rex_user_login' => $user,
                                           'rex_user_psw' => $password));

      $this->updateLoginStatus($response, true);
      
      return $this->loginStatus == 1;
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

      $cookies = array();
      foreach (array_merge($this->cookies, $this->authCookies) as $name => $value) {
        $cookies[] = "$name=$value";
      }
      $cookies = (count($cookies) > 0) ? "Cookie: " . join("; ", $cookies) . "\r\n" : '';

      // Construct the header with any relevant cookies
      $httpHeader = 'Content-Type: application/x-www-form-urlencoded'."\r\n".
        'Content-Length: '.strlen($postData)."\r\n".
        $cookies;

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
      $url  = self::redaxoURL().$formPath;

      \OCP\Util::writeLog(self::APP_NAME, "doSendRequest() to ".$url." data ".$postData, \OC_LOG::DEBUG);

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
          $newAuthHeaders[] = $header;
          $newAuthHeaders[] = preg_replace('|path=([^;]+);?|i', 'path='.\OC::$WEBROOT.'/;', $header);
          $this->authCookies[$match[1]] = $match[2];
          \OCP\Util::writeLog(self::APP_NAME, "Auth Header: ".$header, \OC_LOG::DEBUG);
          \OCP\Util::writeLog(self::APP_NAME, "Rex Cookie: ".$match[1]."=".$match[2], \OC_LOG::DEBUG);
          \OCP\Util::writeLog(self::APP_NAME, "AuthHeaders: ".print_r($this->authHeaders, true), \OC_LOG::DEBUG);          
        } else if (preg_match('|^HTTP/1.[0-9]\s+(30[23])|', $header, $match)) {
          $redirect = true;
          \OCP\Util::writeLog(self::APP_NAME, "Redirect status: ".$match[1], \OC_LOG::DEBUG);
        } else if (preg_match('/^Location:\s*(\S+)$/', $header, $match)) {
          $location = $match[1];
          \OCP\Util::writeLog(self::APP_NAME, "Redirect location: ".$location, \OC_LOG::DEBUG);
        }
      }
      if (count($newAuthHeaders) > 0) {
        $this->authHeaders = $newAuthHeaders;
        \OC::$session->set('Redaxo\\authHeaders', $this->authHeaders);
      }
      //\OCP\Util::writeLog(self::APP_NAME, "Data Response: ".$result, \OC_LOG::DEBUG);

      if ($redirect && $location !== false) {
        // Follow the redirection
        if (substr($location, 0, 4) == 'http') {
          \OCP\Util::writeLog(self::APP_NAME,
                              "Refusing to follow absolute location header: ".$location,
                              \OC_LOG::ERROR);
          return false;
        }
        return self::doSendRequest($location);
      }

      return $result == '' ? false : new Response($responseHdr, $result);
    }


    /**Send a post request corresponding to $postData as post
     * values. Like doSendRequest but only allow if already logged in.
     */
    public function sendRequest($formPath, $postData = false)
    {
      if (!$this->isLoggedIn()) {
        return false;        
      }

      return $this->doSendRequest($formPath, $postData);
    }

    /**Send authentication headers previously aquired
     */
    public function emitAuthHeaders() 
    {
      foreach ($this->authHeaders as $header) {
        \OCP\Util::writeLog(self::APP_NAME, "Emitting auth header: ".$header, \OC_LOG::DEBUG);
        header($header, false /* replace or not??? */);
      }
    }

  };


  /**
   * Simple response wrapper class
   * @author mreinhardt
   *
   */
  class Response {

    private $responseHeaders;

    private $content;

    public function __construct($pHeader, $pContent){
      $this->responseHeaders = $pHeader;
      $this->content = $pContent;
    }

    /**
     *
     * @return http response header ($http_response_header)
     */
    public function getHeaders(){
      return $this->responseHeaders;
    }

    /**
     *
     * @return response content
     */
    public function getContents(){
      return $this->content;
    }
  };

} // namespace

?>
