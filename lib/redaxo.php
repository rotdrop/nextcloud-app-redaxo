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
    public function redaxoURL()
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
        $response = $this->sendRequest($this->location);
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
      $response = $this->sendRequest($this->location.'?rex_logout=1');
      $this->updateLoginStatus($response);
      return $this->loginStatus == -1;
    }

    public function login($user, $password)
    {
      $this->updateLoginStatus();
      
      if ($this->isLoggedIn()) {
        $this->logout();
      }

      $response = $this->sendRequest($this->location,
                                     array('javascript' => 1,
                                           'rex_user_login' => $user,
                                           'rex_user_psw' => $password));

      $this->updateLoginStatus($response, true);
      
      return $this->loginStatus == 1;
    }

    /**Send a post request corresponding to $postData as post
     * values.
     */
    private function sendRequest($formPath, $postData = false)
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

      \OCP\Util::writeLog(self::APP_NAME, "sendRequest() to ".$url." data ".$postData, \OC_LOG::DEBUG);

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
        return self::sendRequest($location);
      }

      return $result == '' ? false : new Response($responseHdr, $result);
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

    /**Move an article to a different category.
     */
    public function moveArticle($articleId, $destCat)
    {
      if (!$this->isLoggedIn()) {
        return false;        
      }

      $result = $this->sendRequest('index.php',
                                   array('article_id' => $articleId,
                                         'page' => 'content', // needed?
                                         'mode' => 'functions',
                                         'save' => 1,
                                         'clang' => 0,
                                         'ctype' => 1,
                                         'category_id_new' => $destCat,
                                         'movearticle' => 'blah', // submit button
                                         'category_copy_id_new' => $articleId,
                                     ));
      if ($result === false) {
        return false;
      }

      // Unlink serialization issue on my Linux box, perhaps a BTRFS
      //issue. Redaxo unlinks files, but apparantly another thread
      //still sees them for more than 1 seconds.
      //sleep(3);

      $reqData = http_build_query(array('article_id' => $articleId,
                                        'page' => 'content', // needed?
                                        'mode' => 'functions',
                                        'clang' => 0,
                                        'ctype' => 1,
                                    ), '', '&');

      $result = $this->sendRequest('index.php'.'?'.$reqData);
      if ($result === false) {
        return false;
      }

      $html = $result->getContents();
      
      /* The result show contain the path with id information:
       * <ul id="rex-navi-path">
       *   <li>Pfad</li>
       *   <li>: <a href="index.php?page=structure&amp;category_id=0&amp;clang=0" tabindex="16">Homepage</a></li>
       *   <li>: <a href="index.php?page=structure&amp;category_id=75&amp;clang=0" tabindex="15">papierkorb</a></li>
       * </ul>
       *
       * We want the values from the last <li> element, of course
       */
      $matches = array();
      $cnt = preg_match('|<ul\s+id="rex-navi-path">\s*'.
                        '(<li>.*</li>)*\s*'.
                        '(<li>[^<]+<a\s+href="index.php\\?page=structure.*'.
                        'category_id=([0-9]+).*'.
                        '</li>)\s*</ul>'.
                        '|si', $html, $matches);
      if ($cnt == 0) {
        return "noMatch";
        return false;
      }
      $actCat = $matches[3];
      
      return $actCat == $destCat;
    }

    /**Delete an article, given its id. To delete all article matching
     * a name, one first has to obtain a list via articlesByName and
     * then delete each one in turn. Seemingly this can be done by a
     * GET, no need for a post. Mmmh.
     */
    public function deleteArticle($articleId, $category)
    {
      if (!$this->isLoggedIn()) {
        return false;        
      }

      $result = $this->sendRequest('index.php',
                                   array(
                                     'page' => 'structure',
                                     'article_id' => $articleId,
                                     'function' => 'artdelete_function',
                                     'category_id' => $category,
                                     'clang' => 0));

      if ($result === false) {
        return false;
      }

      // We could parse the request and have a look if the article is
      // still there ... do it.

      $html = $result->getContents();

      $articles = $this->filterArticlesByName($name, $html);

      if ($articles === false) {
        return false; 
      }

      foreach ($articles as $article) {
        if ($article['article'] == $articleId) {
          return false; // failure 
        }
      }

      return true;
    }

    /**Add a new empty article
     *
     * @param $name The name of the article.
     *
     * @param $category The category id of the article.
     *
     * @param $template The template id of the article.
     *
     * @param $position The position of the article.
     */
    public function addArticle($name, $category, $template, $position = 10000)
    {
      if (!$this->isLoggedIn()) {
        return false;        
      }
      
      $result = $this->sendRequest('index.php',
                                   array( // populate all form fields
                                     'page' => 'structure',
                                     'category_id' => $category,
                                     'clang' => 0, // ???
                                     'template_id' => $template,
                                     'article_name' => $name,
                                     'Position_New_Articel' => $position,
                                     'artadd_function' => 'blah' // should not matter, submit button
                                     ));
      
      if ($result === false) {
        return false;
      }

      $html = $result->getContents();

      return $this->filterArticlesByName($name, $html);
    }

    /**Fetch all matching articles by name. Still, the category has to
     * be given as id.
     */
    public function articlesByName($name, $category)
    {
      if (!$this->isLoggedIn()) {
        return false;        
      }
      
      $result = $this->sendRequest('index.php?page=structure&category_id='.$category.'&clang=0');  
      if ($result === false) {
        return false;
      }
      
      $html = $result->getContents();

      return $this->filterArticlesByName($name, $html);
    }

    /**If the request was successful the response should contain some
     * elements matching the article ID and providing the article
     * ID. The article name is not unique, so we simply check for all
     * lines with the matching article and return an array of ids in
     * success, or false if none is found.
     *
     * We analyze the following element:
     *
     * <td class="rex-icon">
     *   <a class="rex-i-element rex-i-article" href="index.php?page=content&amp;article_id=76&amp;category_id=75&amp;mode=edit&amp;clang=0">
     *     <span class="rex-i-element-text">
     *       blah2014
     *     </span>
     *   </a>
     * </td>
     *
     * We use some preg stuff to detect the two cases. No need to
     * catch the most general case.
     *
     * @param $name Not too complicated regexp
     *
     */
    private function filterArticlesByName($name, $html)
    {
      if ($name == '.*') {
        $name = '[^<]*';
      }

      $matches = array();
      $cnt = preg_match_all('|<a\s+class="rex-i-element\s+rex-i-article"\s+'.
                            'href="index.php\\?'.
                            'page=content[^"]*'.
                            'article_id=([0-9]+)[^"]*'.
                            'category_id=([0-9]+)[^"]*">\s*'.
                            '<span[^>]*>\s*('.$name.')\s*</span>\s*</a>|si', $html, $matches);

      if ($cnt === false || $cnt == 0) {
        return array();
      }
      
      // Fine, we are done. Return an array with the results. Although
      // redundant, we return for each match the triple articleId, categoryId, name
      $result = array();
      for ($i = 0; $i < $cnt; ++$i) {
        $result[] = array('article' => $matches[1][$i],
                          'category' => $matches[2][$i],
                          'name' => $matches[3][$i]);
      }

      // sort ascending w.r.t. to article id
      usort($result, function($a, $b) {
          return $a['article'] < $b['article'] ? -1 : 1;
        });

      return $result;
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
