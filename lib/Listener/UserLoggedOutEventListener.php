<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023, 2025
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

use OCP\User\Events\BeforeUserLoggedOutEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use Psr\Log\LogLevel;

use OCA\Redaxo\Service\AuthRedaxo;

/** Log the current user out of Redaxo if it logs out of Nextcloud. */
class UserLoggedOutEventListener implements IEventListener
{
  use \OCA\Redaxo\Toolkit\Traits\LoggerTrait;
  use \OCA\Redaxo\Toolkit\Traits\ApiRequestTrait;

  const EVENT = HandledEvent::class;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(protected IAppContainer $appContainer)
  {
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!($event instanceof HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    $this->logger = $this->appContainer->get(ILogger::class);

    $request = $this->appContainer->get(IRequest::class);
    if ($this->isNonInteractiveRequest($request, LogLevel::DEBUG)) {
      return;
    }

    try {
      /** @var AuthRedaxo $authenticator */
      $authenticator = $this->appContainer->get(AuthRedaxo::class);
      if ($authenticator->logout()) {
        $authenticator->emitAuthHeaders();
        $this->logInfo("Redaxo logoff probably succeeded.");
      }
      $authenticator->persistLoginStatus();
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to log out of Redxao.');
    }
  }
}
