<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
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

namespace OCA\Redaxo\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\ISession;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\Redaxo\Service\AuthRedaxo as Authenticator;
use OCA\Redaxo\Toolkit\Traits;

/** AJAX end points for periodic authentication refresh. */
class AuthenticationController extends Controller
{
  use Traits\LoggerTrait;

  /** @var ISession */
  private $session;

    /** @var Authenticator */
  private $authenticator;

  /** @var string */
  private $userId;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    ISession $session,
    $userId,
    Authenticator $authenticator,
    ILogger $logger,
    IL10N $l10n,
  ) {
    parent::__construct($appName, $request);
    $this->session = $session;
    $this->userId = $userId;
    $this->authenticator = $authenticator;
    $this->logger = $logger;
    $this->l = $l10n;
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /**
   * @return void
   *
   * @NoAdminRequired
   * @UseSession
   */
  public function refresh():void
  {
    if (false === $this->authenticator->refresh()) {
      $this->logDebug("Redaxo refresh for user ".($this->userId)." failed.");
      $this->authenticator->persistLoginStatus(); // record in session
    } else {
      $this->authenticator->emitAuthHeaders();
      $this->logDebug("Redaxo refresh for user ".($this->userId)." probably succeeded.");
    }
    $this->session->close();
  }
}
