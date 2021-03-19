<?php
/**
 * Redaxo4Embedded -- Embed Redaxo4 into NextCloud with SSO.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\ISession;
use OCP\ILogger;
use OCP\IL10N;

use OCA\Redaxo4Embedded\Service\AuthRedaxo4 as Authenticator;

class AuthenticationController extends Controller
{
  use \OCA\Redaxo4Embedded\Traits\LoggerTrait;

  /** @var ISession */
  private $session;

    /** @var Authenticator */
  private $authenticator;

  /** @var string */
  private $userId;

  public function __construct(
    $appName
    , IRequest $request
    , ISession $session
    , $userId
    , Authenticator $authenticator
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);
    $this->session = $session;
    $this->userId = $userId;
    $this->authenticator = $authenticator;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * @NoAdminRequired
   * @UseSession
   */
  public function refresh()
  {
    if (false === $this->authenticator->refresh()) {
      $this->logError("Redaxo4 refresh for user ".($this->userId)." failed.");
    } else {
      $this->authenticator->emitAuthHeaders();
      $this->logDebug("Redaxo4 refresh for user ".($this->userId)." probably succeeded.");
    }
    // close the session now in order to capture session-already-closed errors
    $this->session->close();
  }
}
