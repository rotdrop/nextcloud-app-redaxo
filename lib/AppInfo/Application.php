<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
 * @license   AGPL-3.0-or-later
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

// phpcs:disable PSR1.Files.SideEffects
// phpcs:ignore PSR1.Files.SideEffects

namespace OCA\Redaxo\AppInfo;

/*-********************************************************
 *
 * Bootstrap
 *
 */

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\App;

use OCP\IConfig;
use OCP\IInitialStateService;

/*
 *
 **********************************************************
 *
 * Events and listeners
 *
 */

use OCP\EventDispatcher\IEventDispatcher;
use OCA\Redaxo\Listener\Registration as ListenerRegistration;

/*
 *
 **********************************************************
 *
 * Assets
 *
 */

use OCA\Redaxo\Service\AssetService;
use OCA\Redaxo\Controller\SettingsController;
use OCA\Redaxo\Constants;

/*
 *
 **********************************************************
 *
 */

include_once __DIR__ . '/../../vendor/autoload.php';

/**
 * App entry point.
 */
class Application extends App implements IBootstrap
{
  use \OCA\Redaxo\Toolkit\Traits\AppNameTrait;

  /** @var string */
  protected $appName;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(array $urlParams = [])
  {
    $this->appName = $this->getAppInfoAppName(__DIR__);
    parent::__construct($this->appName, $urlParams);
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /** @return string */
  public function getAppName()
  {
    return $this->appName;
  }

  /** {@inheritdoc} */
  public function boot(IBootContext $context): void
  {
    $context->injectFn(function(
      IConfig $config,
      IInitialState $initialState,
      IEventDispatcher $dispatcher,
      AssetService $assetService,
    ) {
      $refreshInterval = $config->getAppValue($this->appName, SettingsController::AUTHENTICATION_REFRESH_INTERVAL, 600);
      $dispatcher->addListener(
        \OCP\AppFramework\Http\TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN,
        function() use ($initialState, $refreshInterval, $assetService) {
          $initialState->provideInitialState(
            Constants::INITIAL_STATE_SECTION, [
              'appName' => $this->appName,
              SettingsController::AUTHENTICATION_REFRESH_INTERVAL => $refreshInterval,
            ],
          );
          \OCP\Util::addScript($this->appName, $assetService->getJSAsset('refresh')['asset']);
        }
      );
    });
  }

  /** {@inheritdoc} */
  public function register(IRegistrationContext $context): void
  {
    ListenerRegistration::register($context);
  }
}
