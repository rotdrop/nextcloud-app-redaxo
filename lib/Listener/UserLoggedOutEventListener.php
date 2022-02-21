<?php
/**
 * Redaxo4Embedded -- a Nextcloud App for embedding Redaxo4.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021
 *
 * Redaxo4Embedded is free software: you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * Redaxo4Embedded is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with Redaxo4Embedded.  If not, see
 * <http://www.gnu.org/licenses/>.
 */

namespace OCA\Redaxo4Embedded\Listener;

use OCP\User\Events\BeforeUserLoggedOutEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ILogger;

use OCA\Redaxo4Embedded\Service\AuthRedaxo4;

class UserLoggedOutEventListener implements IEventListener
{
  use \OCA\Redaxo4Embedded\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var AuthRedaxo4 */
  private $authenticator;

  public function __construct(
    AuthRedaxo4 $authenticator
    , ILogger $logger
  ) {
    $this->authenticator = $authenticator;
    $this->appName = $this->authenticator->getAppName();
    $this->logger = $logger;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }

    if ($this->authenticator->logout()) {
      $this->authenticator->emitAuthHeaders();
      $this->logInfo("Redaxo4 logoff probably succeeded.");
    }
    $this->authenticator->persistLoginStatus();
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***
