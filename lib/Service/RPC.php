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

use DOMDocument;
use DOMXPath;
use Throwable;

use OCP\ILogger;
use OCP\IL10N;

/**
 * Remote control via POST and GET for some operations. Probably a real
 * remote protocol would make more sense. Parsing HTML responses is
 * somewhat expensive.
 */
class RPC
{
  use \OCA\Redaxo\Traits\LoggerTrait;

  const ON_ERROR_THROW = AuthRedaxo::ON_ERROR_THROW;
  const ON_ERROR_RETURN = AuthRedaxo::ON_ERROR_RETURN;

  /** @var AuthRedaxo */
  private $authenticator;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    AuthRedaxo $authenticator,
    ILogger $logger,
    IL10N $l10n,
  ) {
    $this->authenticator = $authenticator;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /**
   * Modify how errors are handled.
   *
   * @param null|string $how One of self::ON_ERROR_THROW or
   * self::ON_ERROR_RETURN or null (just return the current
   * reporting).
   *
   * @return string Currently active error handling policy.
   */
  public function errorReporting(?string $how = null):string
  {
    return $this->authenticator->errorReporting($how);
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
    return $this->authenticator->handleError($msg, $throwable);
  }

  /**
   * Return the URL for use with an iframe or object tag. Also
   * provide means to access single articles.
   *
   * @param null|int $articleId
   *
   * @param bool $editMode
   *
   * @return string
   */
  public function redaxoURL(?int $articleId = null, bool $editMode = false):string
  {
    $url = $this->authenticator->externalURL();
    if ($articleId !== null) {
      if ($editMode !== false) {
        $url .= '/index.php?page=content&article_id='.$articleId.'&mode=edit&clang=1';
      } else {
        $url .= '/../?article_id='.$articleId;
      }
    }
    return $url;
  }

  /**
   * Send a post request corresponding to $postData as post
   * values and ensure that we are logged in.
   *
   * @param string $formPath
   *
   * @param null|array $postData
   *
   * @return null|array
   *
   * @see AuthRedaxo::sendRequest()
   */
  public function sendRequest(string $formPath, ?array $postData = null):?array
  {
    // try to login if necessary ...
    if (!$this->authenticator->ensureLoggedIn()) {
      return $this->authenticator->handleError($this->l->t('Not logged in.'));
    }
    return $this->authenticator->sendRequest($formPath, $postData);
  }

  /**
   * Send a dummy request to Redaxo in order to keep the PHP session alive.
   *
   * @return bool
   */
  public function ping():bool
  {
    if ($this->sendRequest('index.php') !== false) {
      // $this->authenticator->persistLoginStatus(); // store in session
      $this->authenticator->emitAuthHeaders(); // send cookies
      return true;
    }
    return false;
  }

  /**
   * Fetch all categories for the Redaxo server.
   *
   * @return null|array
   */
  public function getCategories():?array
  {
    $result = $this->sendRequest('index.php?page=structure');

    if ($result === false) {
      return $this->handleError("Unable to retrieve categories");
    }

    $html = $result['content'];

    $document = new DOMDocument();
    $document->loadHTML($html);

    $categoriesId = 'rex-a256-category-id';
    $query = "//select[@id='".$categoriesId."']/option";
    $categoryOptions = (new DOMXpath($document))->query($query);

    $categories = [];
    $prev = null;
    foreach ($categoryOptions as $categoryOption) {
      // option elements
      $name = str_replace("\xc2\xa0", ' ', preg_replace('/\\s+[[][0-9]+]$/', '', $categoryOption->textContent));
      $indent = strspn($name, " \t\r\n\0\x0B");

      $category = [
        'id' => $categoryOption->getAttribute('value'),
        'name' => ltrim($name),
        'level' => $indent / 3,
        'index' => count($categories),
      ];

      if (empty($prev) || $prev['level'] < $category['level']) {
        $category['parent'] = $prev;
      } elseif ($prev['level'] > $category['level']) {
        $category['parent'] = $prev['parent']['parent'];
      } else /* if ($prev['level'] == $category['level']) */ {
        $category['parent'] = $prev['parent'];
      }
      $prev = $category;

      $categories[] = $category;
    }
    foreach ($categories as &$category) {
      $category['parent'] = empty($category['parent']) ? -1 : $category['parent']['index'];
    }

    $this->logInfo('CATS ' . print-r($categories, true));

    return $categories;
  }

  /**
   * Fetch all templates.
   *
   * @param bool $onlyActive
   *
   * @return null|array
   */
  public function getTemplates(bool $onlyActive = false):?array
  {
    $result = $this->sendRequest('index.php?page=template');

    if ($result === false) {
      return $this->handleError("Unable to retrieve templates");
    }

    $html = $result['content'];

    $document = new DOMDocument();
    $document->loadHTML($html);
    $xPath = new DOMXPath($document);
    $rows = $xPath->query('//tbody/tr');
    $templates = [];
    foreach ($rows as $row) {
      $cols = $xPath->query('td', $row);
      // hard-coded: 1 is id, 2 is name, 3 is active or no
      $id = null;
      $name = null;
      $active = false;
      $index = 0;
      foreach ($cols as $col) {
        $text = $col->textContent;
        switch ($index) {
          case 1:
            $id = (int)$text;
            break;
          case 2:
            $name = $text;
            break;
          case 3:
            $active = $text[0] != 'n';
            break;
        }
        $index++;
      }
      if (!empty($id)) {
        $templates[] = [
          'id' => $id,
          'name' => $name,
          'active' => $active,
        ];
      }
    }
    return $templates;
  }

  /**
   * Fetch all modules.
   *
   * @return null|array
   */
  public function getModules():?array
  {
    $result = $this->sendRequest('index.php?page=module');

    if ($result === false) {
      return $this->handleError("Unable to retrieve modules");
    }

    $html = $result['content'];

    $document = new DOMDocument();
    $document->loadHTML($html);
    $xPath = new DOMXPath($document);
    $rows = $xPath->query('//tbody/tr');
    $modules = [];
    foreach ($rows as $row) {
      $cols = $xPath->query('td', $row);
      // hard-coded: 1 is id, 2 is name
      $id = null;
      $name = null;
      $index = 0;
      foreach ($cols as $col) {
        $text = $col->textContent;
        switch ($index) {
          case 1:
            $id = (int)$text;
            break;
          case 2:
            $name = $text;
            break;
        }
        $index++;
      }
      if (!empty($id)) {
        $modules[] = [
          'id' => $id,
          'name' => $name,
        ];
      }
    }
    return $modules;
  }

  /**
   * Move an article to a different category.
   *
   * @param int $articleId
   *
   * @param int $destCat
   *
   * @return bool
   */
  public function moveArticle(int $articleId, int $destCat):bool
  {
    $result = $this->sendRequest(
      'index.php',
      [ 'article_id' => $articleId,
        'page' => 'content', // needed?
        'mode' => 'functions',
        'save' => 1,
        'clang' => 1,
        'ctype' => 1,
        'category_id_new' => $destCat,
        'movearticle' => 'blah', // submit button
        'category_copy_id_new' => $articleId,
      ]);

    if ($result === false) {
      return $this->handleError("sendRequest() failed.", result: false);
    }

    /*
     * Seemingly there is some potential for race-conditions: moving
     * an article and retrieving the category view directly
     * afterwards display, unfortunately, potentially wrong
     * results. However, Redaxo answers with a status message in the
     * configured backend-language. This is even present in the
     * latest redirected request.
     */
    //<div class=\"rex-message\"><div class=\"rex-info\"><p><span>Artikel wurde verschoben<\/span><\/p><\/div>
    // index.php?page=content&article_id=92&mode=functions&clang=1&ctype=1&info=Artikel+wurde+verschoben

    $redirectReq = $result['request'];

    // $this->logDebug("sendRequest() latest request URI: ".$redirectReq);

    /*
     * Redaxo currently only has de_de and en_gb as backend language, we accept both answers.
     *
     * content_articlemoved = Artikel wurde verschoben
     * content_articlemoved = Article moved.
     */
    $validAnswers = [
      'de_de' => 'Artikel wurde verschoben',
      'en_gb' => 'Article moved.',
    ];
    foreach (array_values($validAnswers) as $answer) {
      $answer = 'info='.urlencode($answer);
      if (strstr($redirectReq, $answer)) {
        return true; // got it, this is a success
      }
    }

    return $this->handleError("rename failed, latest redirect request: " . $redirectReq, result: false);
  }

  /**
   * Delete an article, given its id. To delete all article matching
   * a name, one first has to obtain a list via articlesByName and
   * then delete each one in turn. Seemingly this can be done by a
   * GET, no need for a post. Mmmh.
   *
   * @param int $articleId
   *
   * @param int $categoryId
   *
   * @return bool
   */
  public function deleteArticle(int $articleId, int $categoryId):bool
  {
    $result = $this->sendRequest(
      'index.php',
      [ 'page' => 'structure',
        'article_id' => $articleId,
        'function' => 'artdelete_function',
        'category_id' => $categoryId,
        'clang' => 1 ]);

    if ($result === false) {
      return $this->handleError("Delete article failed", result: false);
    }

    // We could parse the request and have a look if the article is
    // still there ... do it.

    $html = $result['content'];

    $articles = $this->filterArticlesByIdAndName($articleId, '.*', $html);

    // Successful delete: return should be an empty array
    if (!is_array($articles) || count($articles) > 0) {
      return $this->handleError("Delete article failed");
    }
    return true;
  }

  /**
   * Add a new empty article
   *
   * @param string $name The name of the article.
   *
   * @param int $categoryId The category id of the article.
   *
   * @param int $templateId The template id of the article.
   *
   * @param int $position The position of the article.
   *
   * @return null|array
   */
  public function addArticle(string $name, int $categoryId, int $templateId, int $position = 10000):?array
  {
    $result = $this->sendRequest(
      'index.php',
      [  // populate all form fields
        'page' => 'structure',
        'category_id' => $categoryId,
        'clang' => 1, // ???
        'template_id' => $templateId,
        'article_name' => $name,
        'Position_New_Article' => $position,
        'artadd_function' => 'blah' // should not matter, submit button
      ]);

    if ($result === false) {
      return $this->handleError("Adding empty article failed");
    }

    $html = $result['content'];

    return $this->filterArticlesByIdAndName('.*', $name, $html);
  }

  /**
   * Add a block to an existing article.
   *
   * @param int $articleId
   *
   * @param int $blockId
   *
   * @param int $sliceId
   *
   * @return bool
   */
  public function addArticleBlock(int $articleId, int $blockId, int $sliceId = 0):bool
  {
    if (empty($articleId) || empty($blockId)) {
      return $this->handleError($this->l->t('Empty article: / block-id: (%d / %d).', [ $articleId, $blockId ]), result: false);
    }

    $result = $this->sendRequest(
      'index.php',
      [ 'article_id' => $articleId,
        'page' => 'content',
        'mode' => 'edit',
        'slice_id' => $sliceId,
        'function' => 'add',
        'clang' => '1',
        'ctype' => '1',
        'module_id' => $blockId ]);

    if ($result === false) {
      return $this->handleError("Adding article block failed", result: false);
    }

    $html = $result['content'];

    //\OCP\Util::writeLog(App::APP_NAME, "AFTER BLOCK ADD: ".$html, \OC\Util::DEBUG);

    $matches = [];

    // On success we have the following div:
    //<div class="rex-form rex-form-content-editmode-add-slice">
    $addCnt = preg_match_all(
      '/<div\s+class="rex-form\s+rex-form-content-editmode-add-slice">/si',
      $html,
      $matches,
    );

    // Each existing block is surrounded by this div:
    //<div class="rex-content-editmode-slice-output">
    $haveCnt = preg_match_all(
      '/<div\s+class="rex-content-editmode-slice-output">/si',
      $html,
      $matches,
    );

    if ($addCnt != 1) {
      $this->logDebug("Adding block failed, edit-form is missing");
    }

    /*
     * In the case of success we are confonted with an input form
     * with matching hidden form fields. We check for those and then
     * post another query. Hopefully any non submitted data field is
     * simplye treated as empty
     *
     * article_id       122
     * page     content
     * mode     edit
     * slice_id 0
     * function add
     * module_id        2
     * save     1
     * clang    1
     * ctype    1
     * ...
     * BLOCK DATA, we hope we can omit it
     * ...
     * btn_save Block hinzufügen
     */
    $requiredFields = [
      'article_id' => $articleId,
      'page' => 'content',
      'mode' => 'edit',
      'slice_id' => $sliceId,
      'function' => 'add',
      'module_id' => $blockId,
      'save' => 1,
      'clang' => 1,
      'ctype' => 1,
      'btn_save' => 'blah',
    ];
    $target = 'index.php'.'#slice'.$sliceId;

    // passed, send out another query
    $result = $this->sendRequest($target, $requiredFields);

    $html = $result['content'];

    $dummy = [];
    $haveCntAfter = preg_match_all(
      '/<div\s+class="rex-content-editmode-slice-output">/si',
      $html,
      $dummy,
    );

    if ($haveCntAfter != $haveCnt + 1) {
      return $this->handleError("AFTER BLOCK ADD: " . $html, result: false);
    }

    return true;
  }

  /**
   * Change name, base-template and display priority. This command
   * does not alter the written contents of the articel. Compare
   * also addArticel():
   *
   * @param int $articleId
   *
   * @param int $categoryId
   *
   * @param string $name
   *
   * @param int $templateId
   *
   * @param int $position
   *
   * @return null|array
   *
   * @todo Will not work ATM. Not so important, as the web-stuff is
   * tied by id, not by title.
   */
  public function editArticle(int $articleId, int $categoryId, string $name, int $templateId, int $position = 10000):?array
  {
    $result = $this->sendRequest(
      'index.php',
      [
        'page' => 'structure',
        'article_id' => $articleId,
        'category_id' => $categoryId,
        'function' => 'artedit_function',
        'article_name' => $name,
        'template_id' => $templateId,
        'Position_Article' => $position,
        'clang' => 1,
      ]);

    if ($result === false) {
      return $this->handleError("Cannot load form");
    }

    $html = $result['content'];

    // Id should be unique, so the following should just return the
    // one article matching articleId.
    return $this->filterArticlesByIdAndName($articleId, '.*', $html);
  }

  /**
   * Set the article's name to a new value without changing anything
   * else.
   *
   * @param int $articleId
   *
   * @param string $name
   *
   * @return bool
   */
  public function setArticleName(int $articleId, string $name):bool
  {
    $post = [
      "page" => "content",
      "article_id" => $articleId,
      "mode" => "meta",
      "save" => "1",
      "clang" => "1",
      "ctype" => "1",
      "meta_article_name" => $name,
      "savemeta" => "blahsubmit",
    ];

    $result = $this->sendRequest('index.php', $post);

    if ($result === false) {
      return $this->handleError("Unable to set article name", result: false);
    }

    $html = $result['content'];

    // Search for the updated meta_article_name with the new name,
    // and compare the article-id for safety.
    $document = new DOMDocument();
    $document->loadHTML($html);

    $inputs = $document->getElementsByTagName("input");
    $currentId = -1;
    foreach ($inputs as $input) {
      if ($input->getAttribute("name") == "article_id" &&
          $input->getAttribute("value") == $articleId) {
        $currentId = $input->getAttribute("value");
        break;
      }
    }

    if ($currentId != $articleId) {
      return $this->handleError("Changing the article name failed, mis-matched article ids", result: false);
    }

    $input = $document->getElementById("rex-form-meta-article-name");
    $valueName  = $input->getAttribute("name");
    $valueValue = $input->getAttribute("value");

    if ($valueName != "meta_article_name" || $valueValue != $name) {
      return $this->handleError("Changing the article name failed, got ".$valueName.'="'.$valueValue.'"', result: false);
    }

    return true;
  }

  /**
   * Fetch all matching articles by name. Still, the category has to
   * be given as id.
   *
   * @param string $name
   *
   * @param int $categoryId
   *
   * @return null|array
   */
  public function articlesByName(string $name, int $categoryId):?array
  {
    $result = $this->sendRequest('index.php?page=structure&category_id='.$categoryId.'&clang=1');
    if ($result === false) {
      return $this->handleError("Unable to retrieve article by name");
    }

    $html = $result['content'];

    return $this->filterArticlesByIdAndName('.*', $name, $html);
  }

  /**
   * Fetch articles by matching an array of ids
   *
   * @param array $idList Flat array with id to search for. Use the empty
   * array or '.*' to match all articles. Otherwise the elements of
   * idList are used to form a simple regular expression matching
   * the given numerical ids.
   *
   * @param int $categoryId Id of the category (folder) the article belongs to.
   *
   * @return null|array
   * The list of matching articles of false in case of
   * an error. It is no error if no articles match, the returned array
   * is empty in this case.
   */
  public function articlesById(array $idList, int $categoryId):?array
  {
    $result = $this->sendRequest('index.php?page=structure&category_id='.$categoryId.'&clang=1');
    if ($result === false) {
      return $this->handleError("Unable to retrieve articles by id");
    }

    $html = $result['content'];

    return $this->filterArticlesByIdAndName($idList, '.*', $html);
  }

  /**
   * If the request was successful the response should contain some
   * elements matching the category ID and providing the article
   * ID. The article name is not unique, so we simply check for all
   * lines with the matching article and return an array of ids in
   * success, or false if none is found.
   *
   * We analyze the following element:
   *
   * <td class="rex-icon">
   *   <a class="rex-i-element rex-i-article" href="index.php?page=content&amp;article_id=76&amp;category_id=75&amp;mode=edit&amp;clang=1">
   *     <span class="rex-i-element-text">
   *       blah2014
   *     </span>
   *   </a>
   * </td>
   *
   * We use some preg stuff to detect the two cases. No need to
   * catch the most general case.
   *
   * @param array $idList Flat array with id to search for. Use the empty
   * array or '.*' to match all articles. Otherwise the elements of
   * idList are used to form a simple regular expression matching
   * the given numerical ids.
   *
   * @param string $nameRe Regular expression for matching the names. Use
   * '.*' to match all articles.
   *
   * @param string $html
   *
   * @return array List of articles matching the given criteria (both at the
   * same time).
   */
  private function filterArticlesByIdAndName(array $idList, string $nameRe, string $html):array
  {
    if ($nameRe == '.*') {
      $nameRe = '[^<]*';
    }
    if (!is_array($idList)) {
      if ($idList == '.*') {
        $idRe = '[0-9]+';
      }
    } else {
      if (count($idList) == 0) {
        $idRe = '[0-9]+';
      } else {
        $idRe = implode('|', $idList);
      }
    }

    $matches = [];
    $cnt = preg_match_all('|<td\s+class="rex-icon">\s*'.
                          '<a\s+class="rex-i-element\s+rex-i-article"\s+'.
                          'href="index.php\?page=content[^"]*'.
                          'article_id=('.$idRe.')[^"]*'.
                          'category_id=([0-9]+)[^"]*">\s*'.
                          '<span[^>]*>\s*('.$nameRe.')\s*</span>\s*</a>\s*'.
                          '</td>\s*'.
                          '<td\s+class="rex-small">\s*'.
                          '([0-9]+)\s*'.
                          '</td>\s*'.
                          '<td>\s*'.
                          '<a\s+href="index.php\?page=content[^"]*'.
                          'article_id=('.$idRe.')[^"]*'.
                          'category_id=([0-9]+)[^"]*">\s*'.
                          '('.$nameRe.')\s*</a>\s*'.
                          '</td>\s*'.
                          '<td>\s*([0-9]+)\s*</td>\s*'.
                          '<td>\s*([^<]+)\s*</td>'.
                          '|si', $html, $matches);

    if ($cnt === false || $cnt == 0) {
      return [];
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

    $result = [];
    for ($i = 0; $i < $cnt; ++$i) {
      $article = [
        'articleId' => $matches[1][$i],
        'categoryId' => $matches[2][$i],
        'articleName' => $matches[3][$i],
        'priority' => $matches[8][$i],
        'templateName' => trim($matches[9][$i]),
      ];
      $result[] = $article;
      $this->logDebug("Got article: ".print_r($article, true));
    }

    // sort ascending w.r.t. to article id
    usort($result, function($a, $b) {
      return $a['articleId'] < $b['articleId'] ? -1 : 1;
    });

    return $result;
  }
}
