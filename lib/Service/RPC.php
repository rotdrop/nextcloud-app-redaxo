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
use Throwable;

use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

/**
 * Remote control via POST and GET for some operations. Probably a real
 * remote protocol would make more sense. Parsing HTML responses is
 * somewhat expensive.
 */
class RPC
{
  use \OCA\Redaxo\Toolkit\Traits\LoggerTrait;

  const ON_ERROR_THROW = AuthRedaxo::ON_ERROR_THROW;
  const ON_ERROR_RETURN = AuthRedaxo::ON_ERROR_RETURN;

  const API_ARTICLE_MOVE = 'article_move';
  const API_ARTICLE_DELETE = 'article_delete';
  const API_ARTICLE_ADD = 'article_add';
  const API_ARTICLE_EDIT = 'article_edit';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private AuthRedaxo $authenticator,
    protected ILogger $logger,
    private IL10N $l,
  ) {
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
   * @param mixed $articleId Technically null or an integer. However, calling
   * code may pass a string in order to form a template for later substitution
   * or the like.
   *
   * @param bool $editMode
   *
   * @return string
   */
  public function redaxoURL(mixed $articleId = null, bool $editMode = false):string
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
   * @param null|string $csrfKey
   *
   * @return null|array
   *
   * @see AuthRedaxo::sendRequest()
   */
  public function sendRequest(string $formPath, ?array $postData = null, string $csrfKey = AuthRedaxo::LOGIN_CSRF_KEY):?array
  {
    // try to login if necessary ...
    if (!$this->authenticator->ensureLoggedIn()) {
      return $this->authenticator->handleError($this->l->t('Not logged in.'));
    }
    return $this->authenticator->sendRequest($formPath, $postData, $csrfKey);
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
    return $this->authenticator->hasCSRFToken($key);
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
   * Fetch all categories for the Redaxo server. For this to work the
   * "quick_navigation" addon must be installed.
   *
   * @param int $parentId
   *
   * @param int $level
   *
   * @return null|array
   *
   * @todo Replace this by a REST call. However, there is as of now no REST
   * API for Redaxo.
   */
  public function getCategories(int $parentId = -1, int $level = 0):?array
  {
    $url = 'index.php?page=structure&clang=1';
    if ($parentId != -1) {
      $url .= '&category_id=' . $parentId;
    }
    $result = $this->sendRequest($url);

    if ($result === false) {
      return $this->handleError("Unable to retrieve categories");
    }

    $html = $result['content'];

    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR);

    $xPath = new DOMXPath($document);
    $rows = $xPath->query("//tr[contains(@class, 'rex-status')]");

    if (empty($rows)) {
      return [];
    }

    $categories = [];
    /** @var DOMElement $row */
    foreach ($rows as $row) {
      $articleId = $row->getAttribute('data-article-id');
      if (!empty($articleId)) {
        // skip table of articles in this category
        continue;
      }
      $category = [
        'parentId' => $parentId,
        'level' => $level,
      ];
      unset($categoryId);
      /** @var DOMElement $col */
      foreach ($row->getElementsByTagName('td') as $col) {
        $class = $col->getAttribute('class');
        switch (true) {
          case strpos($class, 'rex-table-id') !== false:
            $categoryId = +$col->textContent;
            $category['id'] = $categoryId;
            break;
          case strpos($class, 'rex-table-category') !== false:
            $category['name'] = $col->textContent;
            break;
          default:
            break;
        }
      }
      if (empty($categoryId)) {
        continue;
      }
      $subCategories = $this->getCategories($categoryId, $level + 1);
      $category['children'] = array_map(fn($child) => $child['id'], $subCategories);
      $categories[] = $category;
      $categories = array_merge($categories, $subCategories);
    }

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
    $result = $this->sendRequest('index.php?page=templates');

    if ($result === false) {
      return $this->handleError("Unable to retrieve templates");
    }

    $html = $result['content'];

    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR);
    $xPath = new DOMXPath($document);
    $rows = $xPath->query('//tbody/tr');
    $templates = [];
    foreach ($rows as $row) {
      $cols = $xPath->query('td', $row);
      // hard-coded
      // - 0 is icon
      // - 1 is id
      // - 2 is key
      // - 3 is name
      // - 4 is active or no
      // further are action buttons
      $id = null;
      $name = null;
      $active = false;
      $index = 0;
      /** @var DOMElement $col */
      foreach ($cols as $col) {
        $text = $col->textContent;
        switch ($index) {
          case 1:
            $id = (int)$text;
            break;
          case 3:
            $name = $text;
            break;
          case 4:
            $icons = $col->getElementsByTagName('i');
            /** @var DOMElement $icon */
            foreach ($icons as $icon) {
              $active = strpos($icon->getAttribute('class'), 'rex-icon-active-true') !== false;
            }
            break;
          default:
            break;
        }
        $index++;
      }
      if (empty($id)) {
        continue;
      }
      $template = [
        'id' => $id,
        'name' => $name,
        'active' => $active,
      ];
      $templates[] = $template;
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
    $result = $this->sendRequest('index.php?page=modules');

    if ($result === false) {
      return $this->handleError("Unable to retrieve modules");
    }

    $html = $result['content'];

    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR);
    $xPath = new DOMXPath($document);
    $rows = $xPath->query('//tbody/tr');
    $modules = [];
    foreach ($rows as $row) {
      $cols = $xPath->query('td', $row);
      // hard-coded:
      // - 0 is icon
      // - 1 is id
      // - 2 is key (what is this??)
      // - 3 is name
      // - 4 is active
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
          case 3:
            $name = $text;
            break;
          case 4:
            $icons = $col->getElementsByTagName('i');
            /** @var DOMElement $icon */
            foreach ($icons as $icon) {
              $active = strpos($icon->getAttribute('class'), 'rex-icon-active-true') !== false;
            }
            break;
          default:
            break;
        }
        $index++;
      }
      if (empty($id)) {
        continue;
      }
      $module = [
        'id' => $id,
        'name' => $name,
        'active' => $active,
      ];
      $modules[] = $module;
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
    if (!$this->hasCSRFToken(self::API_ARTICLE_MOVE)) {
      // index.php?page=content/functions&article_id=400&category_id=8&clang=1&ctype=1
      $result = $this->sendRequest('index.php?page=content/functions&article_id=' . $articleId . '&clang=1&ctype=1');
      if ($result === false) {
        return $this->handleError("Moving article failed");
      }
      if (!$this->hasCSRFToken(self::API_ARTICLE_MOVE)) {
        return $this->handleError("Moving article failed, unable to obtain the CSRF token.");
      }
    }

    // Request URI index.php?page=content/functions&article_id=403&category_id=16&clang=1&ctype=1
    //
    // The old category id can be omitted,hopefully
    //
    // Parameters
    // ctype: 1
    // category_id_new: $destCat
    // save: 1
    // article_move: 1
    // category_copy_id_new: $articleId
    // rex-api-call: article_move

    $result = $this->sendRequest(
      'index.php',
      [
        'article_id' => $articleId,
        'page' => 'content/functions',
        'clang' => 1,
        'ctype' => 1,
        // button values
        'save' => 1,
        self::API_ARTICLE_MOVE => 1,
        // real request values
        'rex-api-call' => self::API_ARTICLE_MOVE,
        'category_id_new' => $destCat,
        'category_copy_id_new' => $articleId,
      ],
      csrfKey: self::API_ARTICLE_MOVE,
    );

    if ($result === false) {
      return $this->handleError("sendRequest() failed.", result: false);
    }

    // on success the breadcrumb contains the new category
    $document = $result['document'];
    $xPath = new DOMXPath($document);
    $parentCrumb = $xPath->query("//ol[contains(@class, 'breadcrumb')]/li[position() = last()-1]");
    if (count($parentCrumb) == 1) {
      $parentCrumb = $parentCrumb->item(0);
      /** @var DOMElement $parentCrumb */
      /** @var DOMElement $anchor */
      $anchor = $parentCrumb->getElementsByTagName('a')->item(0);
      $query = parse_url($anchor->getAttribute('href'), PHP_URL_QUERY);
      $data = [];
      parse_str($query, $data);
      $breadCrumbCategory = $data['category_id'] ?? null;
      if ($breadCrumbCategory == $destCat) {
        return true;
      }
    }

    return $this->handleError("Rename failed.", result: false);
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
    // index.php?page=structure&category_id=16&article_id=395&clang=1&artstart=0&rex-api-call=article_delete&_csrf_token=kxSPQaafJKdf3TsUCW-KIyGEMAehcaSyykl4hE94pOQ&_pjax=%23rex-js-page-main
    $result = $this->sendRequest(
      'index.php',
      [
        'rex-api-call' => self::API_ARTICLE_DELETE,
        'article_id' => $articleId,
        'category_id' => $categoryId,
        'page' => 'structure',
        'clang' => 1,
        'artstart' => 0,
      ],
      csrfKey: self::API_ARTICLE_DELETE,
    );

    if ($result === false) {
      return $this->handleError("Delete article failed", result: false);
    }

    // We could parse the request and have a look if the article is
    // still there ... do it.

    $html = $result['content'];
    $articles = $this->findArticlesByIdAndName($articleId, '.*', $categoryId, $html);

    // Successful delete: return should be an empty array
    if (!is_array($articles) || count($articles) > 0) {
      return $this->handleError("Delete article failed " . (is_array($articles) ? print_r($articles, true) : 'NOT AN ARRAY RESPONSE'));
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
    // At first we may have to obtain or update the CSRF token for adding
    // articles. Interestingly the action requesting the token itself is not
    // protected by another CSRF token.
    if (!$this->hasCSRFToken(self::API_ARTICLE_ADD)) {
      // index.php?page=structure&category_id=2&article_id=2&clang=1&function=add_art&artstart=0&_pjax=%23rex-js-page-main
      $result = $this->sendRequest('index.php?page=structure&clang=1&function=add_art&category_id=' . $categoryId);
      if ($result === false) {
        return $this->handleError("Adding empty article failed");
      }
      if (!$this->hasCSRFToken(self::API_ARTICLE_ADD)) {
        return $this->handleError("Adding empty article failed, unable to obtain the CSRF token.");
      }
    }

    $data = [
      'article-name' => $name,
      'template_id' => $templateId,
      'article-position' => $position,
      'rex-api-call' => self::API_ARTICLE_ADD,
      'art_add_function' => '',
    ];
    $result = $this->sendRequest('index.php?page=structure&category_id=' . $categoryId . '&article_id=0&clang=1&artstart=0', $data, csrfKey: self::API_ARTICLE_ADD);

    if ($result === false) {
      return $this->handleError("Adding empty article failed");
    }

    $html = $result['content'];

    $articles = $this->findArticlesByIdAndName('.*', $name, $categoryId, $html);

    if (empty($articles)) {
      return $this->handleError("Adding empty article failed");
    }

    // $this->logInfo('ARTICLES ' . print_r($articles, true));

    return $articles;
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
  public function addArticleBlock(int $articleId, int $blockId, int $sliceId = -1):bool
  {
    if (empty($articleId) || empty($blockId)) {
      return $this->handleError($this->l->t('Empty article: / block-id: (%d / %d).', [ $articleId, $blockId ]), result: false);
    }

    // Starting the editor
    //
    // index.php?page=content/edit&article_id=403&clang=1&ctype=1&slice_id=-1&function=add&module_id=2&_pjax=%23rex-js-page-main-content#slice-add-pos-1


    $result = $this->sendRequest(
      'index.php',
      [ 'article_id' => $articleId,
        'page' => 'content/edit',
        'slice_id' => $sliceId,
        'function' => 'add',
        'clang' => '1',
        'ctype' => '1',
        'module_id' => $blockId ]);

    if ($result === false) {
      return $this->handleError('Adding article block failed.', result: false);
    }

    $document = $result['document'];
    $xPath = new DOMXPath($document);
    $slices = $xPath->query("//ul[contains(@class, 'rex-slices')]/li[contains(@class, 'rex-slice-add')]");
    $addCnt = count($slices);

    $this->logDebug('ADD SLICES ' . $addCnt);

    $slices = $xPath->query("//ul[contains(@class, 'rex-slices')]/li[contains(@class, 'rex-slice-output')]");
    $haveCnt = count($slices);

    $this->logDebug('HAVE SLICES ' . $haveCnt);

    if ($addCnt != 1) {
      return $this->handleError('Adding block failed, edit-form is missing.', result: false);
    }

    // Submit request-url:
    // index.php?page=content/edit&article_id=403&slice_id=-1&clang=1&ctype=1#slice-add-pos-0
    //
    // Submit post data:
    // function: add
    // module_id: ID
    // save: 1
    // btn_save: 1
    //
    // + input values which can be omitted as we do not add data here.

    $requiredFields = [
      // POST
      'function' => 'add',
      'module_id' => $blockId,
      'save' => 1,
      'btn_save' => 1,
      // GET, just also put it in the post-data
      'page' => 'content/edit',
      'article_id' => $articleId,
      'slice_id' => -1,
      'clang' => 1,
      'ctype' => 1,
    ];
    $target = 'index.php' . '#slice-add-pos-1';

    // passed, send out another query
    $result = $this->sendRequest($target, $requiredFields);

    $document = $result['document'];
    $xPath = new DOMXPath($document);

    $slices = $xPath->query("//ul[contains(@class, 'rex-slices')]/li[contains(@class, 'rex-slice-output')]");
    $haveCntAfter = count($slices);

    $this->logDebug('HAVE COUNT AFTER ' . $haveCntAfter);

    if ($haveCntAfter != $haveCnt + 1) {
      return $this->handleError('Adding block failed, new block is not there.', result: false);
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
    if (!$this->hasCSRFToken(self::API_ARTICLE_EDIT)) {
      // index.php?page=structure&category_id=80&article_id=266&clang=1&function=edit_art&artstart=0&_pjax=%23rex-js-page-main
      $result = $this->sendRequest('index.php?page=structure&article_id=' . $articleId . '&clang=1&function=edit_art');
      if ($result === false) {
        return $this->handleError('Unable to trigger CSRF update');
      }
      if (!$this->hasCSRFToken(self::API_ARTICLE_EDIT)) {
        return $this->handleError('Unable to obtain the CSRF token.');
      }
    }

    // Submit URL
    // index.php?page=structure&category_id=80&article_id=266&clang=1&artstart=0
    // POST data
    // article-name: NAME
    // template_id:
    // article-position: pos
    // rex-api-call: article_edit
    // artedit_function ''

    $result = $this->sendRequest(
      'index.php',
      [
        'page' => 'structure',
        'article_id' => $articleId,
        'category_id' => $categoryId,
        'function' => 'artedit_function',
        'article_name' => $name,
        'template_id' => $templateId,
        'article_position' => $position,
        'rex-api-call' => self::API_ARTICLE_EDIT,
        'clang' => 1,
        'artstart' => 0,
      ]);

    if ($result === false) {
      return $this->handleError("Cannot load form");
    }

    $html = $result['content'];

    // Id should be unique, so the following should just return the
    // one article matching articleId.
    return $this->findArticlesByIdAndName($articleId, '.*', $categoryId, $html);
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
    // index.php?page=content/functions&article_id=266&clang=1&ctype=1

    $post = [
      "page" => "content/functions",
      "article_id" => $articleId,
      "clang" => "1",
      "ctype" => "1",
      "meta_article_name" => $name,
      "savemeta" => "1",
      "save" => "1",
    ];

    $result = $this->sendRequest('index.php', $post);

    if ($result === false) {
      return $this->handleError("Unable to set article name", result: false);
    }

    // Search for the updated meta_article_name with the new name,
    // and compare the article-id for safety.

    $document = $result['document'];

    $metaForm = $document->getElementById('rex-form-content-metamode');
    $query = parse_url($metaForm->getAttribute('action'), PHP_URL_QUERY);
    $data = [];
    parse_str($query, $data);
    $currentId = $data['article_id'];

    if ($currentId != $articleId) {
      return $this->handleError('Changing the article name failed, mis-matched article ids', result: false);
    }

    $input = $document->getElementById("rex-id-meta-article-name");
    $valueName  = $input->getAttribute("name");
    $valueValue = $input->getAttribute("value");

    if ($valueName != 'meta_article_name' || $valueValue != $name) {
      return $this->handleError('Changing the article name failed, got ' . $valueName . ' = "' . $valueValue . '"', result: false);
    }

    return true;
  }

  /**
   * Find the next chunk by parsing the pagination controls of the Redaxo
   * output.
   *
   * @param string $html
   *
   * @return int
   */
  private function findNextChunk(string $html)
  {
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR);

    $xPath = new DOMXPath($document);
    $pagination = $xPath->query("//ul[contains(@class, 'pagination')]/li[last()]");
    if (empty($pagination) || count($pagination) == 0) {
      return -1;
    }

    /** @var DOMElement $nextItem */
    $nextItem = $pagination->item(0);
    if (strpos($nextItem->getAttribute('class'), 'disabled') !== false) {
      return -1; // no next page
    }

    $anchors = $nextItem->getElementsByTagName('a');
    if (count($anchors) !== 1) {
      return -1;
    }

    $href = $anchors->item(0)->getAttribute('href');
    if (preg_match('/artstart=([0-9]+)/', $href, $matches)) {
      return (int)$matches[1];
    }

    return -1;
  }

  /**
   * Fetch all matching articles by name. Still, the category has to
   * be given as id.
   *
   * @param string $nameRe A regular expression without delimiters for the
   * matching article name. Use '.*' to match all articles.
   *
   * @param int $categoryId
   *
   * @return null|array
   */
  public function articlesByName(string $nameRe, int $categoryId):?array
  {
    return $this->findArticlesByIdAndName('.*', $nameRe, $categoryId);
  }

  /**
   * Fetch articles by matching an array of ids
   *
   * @param string|array $idList Flat array with id to search for. Use the empty
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
  public function articlesById(string|array $idList, int $categoryId):?array
  {
    return $this->findArticlesByIdAndName($idList, '.*', $categoryId);
  }

  /**
   * Find articles by id and/or name. Internally this has to fetch all
   * articles for the given $categoryId and filter out the non-matching
   * articles.
   *
   * @param int|string|array $idList Flat array or string with id criteria to
   * search for. Use the empty array or '.*' to match all articles. Otherwise
   * the elements of idList are used to form a simple regular expression
   * matching the given numerical ids.
   *
   * @param string $nameRe Regular expression for matching the names. Use
   * '.*' to match all articles.
   *
   * @param int $categoryId Id of the category (folder) the article belongs to.
   *
   * @param null|string $initialRequestResponse If non-null the initial
   * request is skipped and the given string is assumed to contain a HTML
   * response to a previous request.
   *
   * @return null|array
   * The list of matching articles of false in case of
   * an error. It is no error if no articles match, the returned array
   * is empty in this case.
   */
  public function findArticlesByIdAndName(
    int|string|array $idList,
    string $nameRe,
    int $categoryId,
    ?string $initialRequestResponse = null,
  ):?array {

    // if $idList really refers to a single id then stop on the first matching
    // article.
    if (is_string($idList) && is_numeric($idList) && $idList == (int)$idList) {
      $idList = (int)$idList;
    }
    $stopOnFirstMatch = is_int($idList);

    $articles = [];
    $artStart = 0;
    do {
      if (empty($initialRequestResponse)) {
        $result = $this->sendRequest('index.php?page=structure&category_id=' . $categoryId . '&clang=1&artstart=' . $artStart);
        if ($result === false) {
          return $this->handleError("Unable to retrieve article by name");
        }
        $html = $result['content'];
      } else {
        $html = $initialRequestResponse;
        $initialRequestResponse = null;
      }

      $articles = array_merge($articles, $this->filterArticlesByIdAndName($idList, $nameRe, $html));

      if ($stopOnFirstMatch && !empty($articles)) {
        break;
      }

      $artStart = $this->findNextChunk($html);

    } while ($artStart > 0);

    return $articles;
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
   * @param string|array $idList Flat array or string with id criteria to
   * search for. Use the empty array or '.*' to match all articles. Otherwise
   * the elements of idList are used to form a simple regular expression
   * matching the given numerical ids.
   *
   * @param string $nameRe Regular expression for matching the names. Use
   * '.*' to match all articles.
   *
   * @param string $html
   *
   * @return array List of articles matching the given criteria (both at the
   * same time).
   */
  private function filterArticlesByIdAndName(string|array $idList, string $nameRe, string $html):array
  {
    if (!is_array($idList)) {
      if ($idList == '.*') {
        $idRe = '[0-9]+';
      } else {
        $idRe = $idList;
      }
    } else {
      if (count($idList) == 0) {
        $idRe = '[0-9]+';
      } else {
        $idRe = implode('|', $idList);
      }
    }

    $nameRe = '@' . $nameRe . '@';
    $idRe = '@' . $idRe . '@';

    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_NOERROR);

    $xPath = new DOMXPath($document);
    $rows = $xPath->query("//tr[contains(@class, 'rex-status')]");

    if (empty($rows)) {
      return [];
    }

    $result = [];
    /** @var DOMElement $row */
    foreach ($rows as $row) {
      $articleId = $row->getAttribute('data-article-id');
      if (empty($articleId) || !preg_match($idRe, $articleId)) {
        continue;
      }
      $article = [
        'articleId' => $articleId,
      ];
      // $this->logInfo('STATUS ' . $rowStatus . ' ID ' . $articleId . ' CLASS ' . $row->getAttribute('class'));
      /** @var DOMElement $col */
      foreach ($row->getElementsByTagName('td') as $col) {
        $class = $col->getAttribute('class');
        switch (true) {
          case strpos($class, 'rex-table-icon') !== false:
            break;
          case strpos($class, 'rex-table-article-name') !== false:
            $articleName = $col->textContent;
            if (!preg_match($nameRe, $articleName)) {
              continue 3; // outer loop
            }
            // seemingly textContent only contains the innermost text.
            $article['articleName'] = $col->textContent;
            break;
          case strpos($class, 'rex-table-priority') !== false:
            $article['priority'] = $col->textContent;
            break;
          case strpos($class, 'rex-table-template') !== false:
            $article['templateName'] = $col->textContent;
            break;
          default:
            break;
        }
        if (empty($article['categoryId'])) {
          // the category id is contained in various href attibutes.
          $anchors = $col->getElementsByTagName('a');
          /** @var DOMElement $anchor */
          foreach ($anchors as $anchor) {
            $query = parse_url($anchor->getAttribute('href'), PHP_URL_QUERY);
            $data = [];
            parse_str($query, $data);
            $categoryId = $data['category_id'] ?? null;
            if (!empty($categoryId)) {
              $article['categoryId'] = $categoryId;
              break;
            }
          }
        }
      }
      $result[] = $article;
    }

    // sort ascending w.r.t. to article id
    usort($result, function($a, $b) {
      return $a['articleId'] < $b['articleId'] ? -1 : 1;
    });

    return $result;
  }
}
