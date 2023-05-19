<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
 * @license LGPL
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

namespace OCA\Redaxo\Controller;

use Exception;

use OCP\IRequest;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IInitialStateService;

use OCA\Redaxo\Traits;
use OCA\Redaxo\Service\AuthRedaxo as Authenticator;
use OCA\Redaxo\Exceptions\LoginException;

class PageController extends Controller
{
  use Traits\LoggerTrait;
  use Traits\ResponseTrait;

  const TEMPLATE = 'redaxo';

  /** @var string */
  private $userId;

  /** @var Authenticator */
  private $authenticator;

  /** @var IConfig */
  private $config;

  /** @var IURLGenerator */
  private $urlGenerator;

  /** @var IInitialStateService */
  private $initialStateService;

  /** @var ISession */
  private $session;

  public function __construct(
    $appName
    , IRequest $request
    , ISession $session
    , Authenticator $authenticator
    , IConfig $config
    , IURLGenerator $urlGenerator
    , IInitialStateService $initialStateService
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);
    $this->session = $session;
    $this->authenticator = $authenticator;
    $this->authenticator->errorReporting(Authenticator::ON_ERROR_THROW);
    $this->config = $config;
    $this->urlGenerator = $urlGenerator;
    $this->initialStateService = $initialStateService;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * @NoAdminRequired
   * @NoCSRFRequired
   * @UseSession
   */
  public function index()
  {
    return $this->frame('user');
  }

  /**
   * @NoAdminRequired
   * @UseSession
   */
  public function frame($renderAs = 'blank')
  {
    try {
      $this->initialStateService->provideInitialState(
        $this->appName,
        'initial',
        [
          'appName' => $this->appName,
          'refreshInterval' => $this->config->getAppValue('refreshInterval', 600),
        ]
      );

      $externalURL  = $this->authenticator->externalURL();
      $externalPath = $this->request->getParam('externalPath', '');
      $cssClass     = $this->request->getParam('cssClass', 'fullscreen');

      if (empty($externalURL)) {
        // @TODO wrap into a nicer error page.
        throw new Exception($this->l->t('Please tell a system administrator to configure the URL for the Redaxo instance'));
      }
      try {
        $this->authenticator->ensureLoggedIn(true);
        $this->authenticator->persistLoginStatus(); // store in session
        $this->authenticator->emitAuthHeaders(); // send cookies
      } catch (\Throwable $t) {
        $this->logException($t, 'Unable to log into Redaxo');
        $this->authenticator->persistLoginStatus(); // store in session
      }
      $this->session->close(); // flush session to disk

      $templateParameters = [
        'appName'          => $this->appName,
        'externalURL'      => $externalURL,
        'externalPath'     => $externalPath,
        'cssClass'         => $cssClass,
        'iframeAttributes' => '',
        'urlGenerator'     => $this->urlGenerator,
      ];

      $response = new TemplateResponse(
        $this->appName,
        self::TEMPLATE,
        $templateParameters,
        $renderAs);

      $policy = new ContentSecurityPolicy();
      $policy->addAllowedChildSrcDomain('*');
      $policy->addAllowedFrameDomain('*');
      $response->setContentSecurityPolicy($policy);

      return $response;

    } catch (\Throwable $t) {
      if ($renderAs == 'blank') {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      } else {
        throw $t;
      }
    }


  }
}
