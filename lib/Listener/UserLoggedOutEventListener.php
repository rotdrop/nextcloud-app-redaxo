<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
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
use OCP\ILogger;

use OCA\Redaxo\Service\AuthRedaxo;

class UserLoggedOutEventListener implements IEventListener
{
  use \OCA\Redaxo\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var OCP\IRequest */
  private $request;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(
    IRequest $request,
    ILogger $logger,
    IAppContainer $appContainer,
  ) {
    $this->request = $request;
    $this->logger = $logger;
    $this->appContainer = $appContainer;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }

    if ($this->ignoreRequest($this->request)) {
      return;
    }

    /** @var AuthRedaxo $authenticator */
    $authenticator = $this->appContainer->get(AuthRedaxo::class);

    if ($authenticator->logout()) {
      $authenticator->emitAuthHeaders();
      // $this->logInfo("Redaxo logoff probably succeeded.");
    }
    $authenticator->persistLoginStatus();
  }

  /**
   * In order to avoid request ping-pong the auto-login should only be
   * attempted for UI logins.
   */
  private function ignoreRequest(IRequest $request):bool
  {
    $method = $request->getMethod();
    if ($method != 'GET' && $method != 'POST') {
      $this->logDebug('Ignoring request with method '.$method);
      return true;
    }
    if ($request->getHeader('OCS-APIREQUEST') === 'true') {
      $this->logDebug('Ignoring API login');
      return true;
    }
    if (strpos($request->getHeader('Authorization'), 'Bearer ') === 0) {
      $this->logDebug('Ignoring API "bearer" auth');
      return true;
    }
    return false;
  }
}
