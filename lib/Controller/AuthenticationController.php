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
      $this->logDebug("Redaxo4 refresh for user ".($this->userId)." failed.");
      $this->authenticator->persistLoginStatus(); // record in session
    } else {
      $this->authenticator->emitAuthHeaders();
      $this->logDebug("Redaxo4 refresh for user ".($this->userId)." probably succeeded.");
    }
    $this->session->close();
  }
}
