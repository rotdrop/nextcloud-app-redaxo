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

  /**Remote control via POST and GET for some operation. Probably a
   * real remote protocol would make more sense. Parsing HTML
   * responses is somewhat expensive.
   */
  class RPC
  {
    private $app;

    public function __construct($rex)
    {
      if ($rex instanceof App) {
        $this->app = $rex;
      } else {
        $this->app = new App($rex);
      }
    }
    
    /**Return the URL for use with an iframe or object tag. Also
     * provide means to access single articles.
     */
    public function redaxoURL($articleId = false, $editMode = false)
    {
      $url = $this->app->redaxoURL();
      if ($articleId !== false) {
        if ($editMode !== false) {
          $url .= 'index.php?page=content&article_id='.$articleId.'&mode=edit&clang=0';
        } else {
          $url .= '../?article_id='.$articleId;
        }
      }
      return $url;
    }

    /**Move an article to a different category.
     */
    public function moveArticle($articleId, $destCat)
    {
      $result = $this->app->sendRequest('index.php',
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

      $result = $this->app->sendRequest('index.php'.'?'.$reqData);
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
      $result = $this->app->sendRequest('index.php',
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
      $result = $this->app->sendRequest(
        'index.php',
        array( // populate all form fields
          'page' => 'structure',
          'category_id' => $category,
          'clang' => 0, // ???
          'template_id' => $template,
          'article_name' => $name,
          'Position_New_Article' => $position,
          'artadd_function' => 'blah' // should not matter, submit button
          ));
      
      if ($result === false) {
        return false;
      }

      $html = $result->getContents();

      return $this->filterArticlesByName($name, $html);
    }

    /**Add a block to an existing article */
    public function addArticleBlock($articleId, $blockId, $sliceId = 0)
    {
      $result = $this->app->sendRequest(
        'index.php',
        array('article_id' => $articleId,
              'page' => 'content',
              'mode' => 'edit',
              'slice_id' => $sliceId,
              'function' => 'add',
              'clang' => '0',
              'ctype' => '1',
              'module_id' => $blockId));
      
      if ($result === false) {
        return false;
      }

      $html = $result->getContents();

      //\OCP\Util::writeLog(App::APP_NAME, "AFTER BLOCK ADD: ".$html, \OC_LOG::DEBUG);

      $matches = array();
      $cnt = preg_match_all('/<div\s+class="rex-form\s+rex-form-content-editmode-add-slice">/si',
                            $html, $matches);      

      // On success we have the following div:
      //<div class="rex-form rex-form-content-editmode-add-slice">
      $addCnt = preg_match_all('/<div\s+class="rex-form\s+rex-form-content-editmode-add-slice">/si',
                               $html);

      // Each existing block is surrounded by this div:
      //<div class="rex-content-editmode-slice-output">
      $haveCnt = preg_match_all('/<div\s+class="rex-content-editmode-slice-output">/si',
                                $html);
      
      if ($addCnt != 1) {
        \OCP\Util::writeLog(App::APP_NAME, "Adding block failed, edit-form is missing", \OC_LOG::DEBUG);
      }

      /* In the case of success we are confonted with an input form
       * with matching hidden form fields. We check for those and then
       * post another query. Hopefully any non submitted data field is
       * simplye treated as empty
       * 
       * article_id	122
       * page	content
       * mode	edit
       * slice_id	0
       * function	add
       * module_id	2
       * save	1
       * clang	0
       * ctype	1
       * ...
       * BLOCK DATA, we hope we can omit it
       * ...
       * btn_save	Block hinzufügen
       */
      $requiredFields = array('article_id' => $articleId,
                              'page' => 'content',
                              'mode' => 'edit',
                              'slice_id' => $sliceId,
                              'function' => 'add',
                              'module_id' => $blockId,
                              'save' => 1,
                              'clang' => 0,
                              'ctype' => 1,
                              'btn_save' => 'blah');

      // passed, send out another query
      $result = $this->app->sendRequest($target, $requiredFields);

      $html = $result->getContents();

      $haveCntAfter = preg_match_all('/<div\s+class="rex-content-editmode-slice-output">/si',
                                     $html);

      if ($haveCntAfter != $haveCnt + 1) {
        \OCP\Util::writeLog(App::APP_NAME, "AFTER BLOCK ADD: ".$html, \OC_LOG::DEBUG);
        return false;
      }

      return true;
    }

    /**Change name, base-template and display priority. This command
     * does not alter the written contents of the articel. Compare
     * also addArticel():
     *
     * TODO: will not work ATM. Not so important, as the web-stuff is
     * tied by id, not by title.
     */
    public function editArticle($article, $category, $name, $template, $position = 10000)
    {
      $result = $this->app->sendRequest('index.php',
                                        array(
                                          'page' => 'structure',
                                          'category_id' => $category,
                                          'article_id' => $article,
                                          'category_id' => $category,
                                          'function' => 'artedit_function',
                                          'article_name' => $name,
                                          'template_id' => $template,
                                          'Position_Article' => $position,
                                          'clang' => 0));
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
      $result = $this->app->sendRequest('index.php?page=structure&category_id='.$category.'&clang=0');  
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
      $cnt = preg_match_all('|<td\s+class="rex-icon">\s*'.
                            '<a\s+class="rex-i-element\s+rex-i-article"\s+'.
                            'href="index.php\\?page=content[^"]*'.
                            'article_id=([0-9]+)[^"]*'.
                            'category_id=([0-9]+)[^"]*">\s*'.
                            '<span[^>]*>\s*('.$name.')\s*</span>\s*</a>\s*'.
                            '</td>\s*'.
                            '<td\s+class="rex-small">\s*'.
                            '([0-9]+)\s*'.
                            '</td>\s*'.
                            '<td>\s*'.
                            '<a\s+href="index.php\\?page=content[^"]*'.
                            'article_id=([0-9]+)[^"]*'.
                            'category_id=([0-9]+)[^"]*">\s*'.
                            '('.$name.')\s*</a>\s*'.
                            '</td>\s*'.
                            '<td>\s*([0-9]+)\s*</td>\s*'.
                            '<td>\s*([^<]+)\s*</td>'.
                            '|si', $html, $matches);

      if ($cnt === false || $cnt == 0) {
        return array();
      }
      
      /* match[1]: article ids
       * match[2]: category
       * match[3]: name
       * match[4]: article ids
       * match[5]: article ids
       * match[6]: category
       * match[7]: name
       * match[8]: display priority
       * match[9]: template name (but not id, unfortunately)
       */

      $result = array();
      for ($i = 0; $i < $cnt; ++$i) {
        $result[] = array('article' => $matches[1][$i],
                          'category' => $matches[2][$i],
                          'name' => $matches[3][$i],
                          'priority' => $matches[8][$i],
                          'template' => trim($matches[9][$i]));
      }

      // sort ascending w.r.t. to article id
      usort($result, function($a, $b) {
          return $a['article'] < $b['article'] ? -1 : 1;
        });

      return $result;
    }
    

  };
  

} // namespace

?>
