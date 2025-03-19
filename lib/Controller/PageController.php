<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023, 2025
 * @license AGPL
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\Util;
use Psr\Log\LoggerInterface as ILogger;

use OCA\Redaxo\Constants;
use OCA\Redaxo\Exceptions\LoginException;
use OCA\Redaxo\Service\AssetService;
use OCA\Redaxo\Service\AuthRedaxo as Authenticator;
use OCA\Redaxo\Toolkit\Traits;

/** Main entry point for web frontend. */
class PageController extends Controller
{
  use Traits\LoggerTrait;
  use Traits\ResponseTrait;

  const TEMPLATE = 'app';
  const ASSET = 'app';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    private ISession $session,
    private Authenticator $authenticator,
    private AssetService $assetService,
    private IConfig $config,
    private IInitialStateService $initialStateService,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /**
   * @return Response
   *
   * @NoAdminRequired
   * @NoCSRFRequired
   */
  public function index():TemplateResponse
  {
    $externalURL  = $this->authenticator->externalURL();
    $externalPath = $this->request->getParam('externalPath', '');

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
    $this->session->close(); // flush session to disk <- is this still needed?

    $this->initialStateService->provideInitialState(
      $this->appName,
      Constants::INITIAL_STATE_SECTION,
      [
        'appName' => $this->appName,
        'externalLocation' => $externalURL . $externalPath,
        SettingsController::AUTHENTICATION_REFRESH_INTERVAL => $this->config->getAppValue(SettingsController::AUTHENTICATION_REFRESH_INTERVAL, 600),
      ]
    );

    Util::addScript($this->appName, $this->assetService->getJSAsset(self::ASSET)['asset']);
    Util::addStyle($this->appName, $this->assetService->getCSSAsset(self::ASSET)['asset']);

    $response = new TemplateResponse($this->appName, self::TEMPLATE, []);

    $urlParts = parse_url($externalURL);
    $externalHost = $urlParts['host'];

    $policy = new ContentSecurityPolicy();
    $policy->addAllowedChildSrcDomain($externalHost);
    $policy->addAllowedFrameDomain($externalHost);
    $response->setContentSecurityPolicy($policy);

    return $response;
  }
}
