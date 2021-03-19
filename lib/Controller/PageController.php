<?php
/**
 * Redaxo4Embedded -- Embed DokuWik into NextCloud with SSO.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\Redaxo4Embedded\Controller;

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

use OCA\Redaxo4Embedded\Traits;
use OCA\Redaxo4Embedded\Service\AuthRedaxo4 as Authenticator;

class PageController extends Controller
{
  use Traits\LoggerTrait;
  use Traits\ResponseTrait;

  const TEMPLATE = 'redaxo4';

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
        throw new \Exception($this->l->t('Please tell a system administrator to configure the URL for the Redaxo4 instance'));
      }
      $this->authenticator->sendRequest('');   // update login
      $this->authenticator->emitAuthHeaders(); // send cookies

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
      if ($renderAS == 'blank') {
        $this->logException($t);
        return self::grumble($this->exceptionChainData($t));
      } else {
        throw $t;
      }
    }


  }
}
