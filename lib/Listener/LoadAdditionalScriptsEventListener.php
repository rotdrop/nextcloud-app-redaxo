<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2024, 2025
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

namespace OCA\Redaxo\Listener;

use Throwable;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use Psr\Log\LogLevel;
use OCP\IConfig;
use OCP\AppFramework\Services\IInitialState;

use OCA\Redaxo\Service\AssetService;
use OCA\Redaxo\Controller\SettingsController;
use OCA\Redaxo\Constants;

/** Load additional scripts while running interactively. */
class LoadAdditionalScriptsEventListener implements IEventListener
{
  use \OCA\Redaxo\Toolkit\Traits\LoggerTrait;
  use \OCA\Redaxo\Toolkit\Traits\ApiRequestTrait;

  private const INITIAL_STATE_SECTION = 'authRefresh';
  public const EVENT = HandledEvent::class;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(protected IAppContainer $appContainer)
  {
    $this->logger = $this->appContainer->get(ILogger::class);
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $this->logger = $this->appContainer->get(ILogger::class);
    if (!($event instanceof HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    $this->logger = $this->appContainer->get(ILogger::class);

    $request = $this->appContainer->get(IRequest::class);
    if ($this->isNonInteractiveRequest($request, LogLevel::DEBUG)) {
      return;
    }

    if (!$event->isLoggedIn()) {
      // this app does not provide any public pages
      return;
    }

    try {
      /** @var IConfig $config */
      $config = $this->appContainer->get(IConfig::class);

      /** @var IInitialState $initialState */
      $initialState = $this->appContainer->get(IInitialState::class);

      /** @var string $appName */
      $appName = $this->appContainer->get('appName');

      $refreshInterval = $config->getAppValue($appName, SettingsController::AUTHENTICATION_REFRESH_INTERVAL, 600);

      $initialState->provideInitialState(
        self::INITIAL_STATE_SECTION, [
          'appName' => $appName,
          SettingsController::AUTHENTICATION_REFRESH_INTERVAL => $refreshInterval,
        ],
      );

      /** @var AssetService $assetService */
      $assetService = $this->appContainer->get(AssetService::class);

      \OCP\Util::addScript($appName, $assetService->getJSAsset('refresh')['asset']);
      $this->logDebug('Loaded ' . $assetService->getJSAsset('refresh')['asset']);
    } catch (Throwable $t) {
      $this->logException($t, 'Unable add the refresh java script while running interactively.');
    }
  }
}
