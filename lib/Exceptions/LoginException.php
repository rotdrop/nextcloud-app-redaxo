<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
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

namespace OCA\Redaxo\Exceptions;

use RuntimeException;
use Throwable;
use OCA\Redaxo\Enums\LoginStatusEnum;

/** Login-error exception base class. */
class LoginException extends RuntimeException
{
  /** @var string */
  protected $originalMessage;

  /** @var string|null */
  protected $userId;

  /** @var LoginStatusEnum */
  protected $loginStatus;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $message,
    int $code = 0,
    Throwable $previous = null,
    ?string $userId = null,
    ?LoginStatusEnum $loginStatus = null,
  ) {
    parent::__construct($this->message, $code, $previous);
    $this->originalMessage = $message;
    $this->userId = $userId;
    $this->loginStatus = $loginStatus?: LoginStatusEnum::UNKNOWN();
    $this->generateMessage();
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /**
   * Generate a suitable message based on the supplied data.
   *
   * @return void
   */
  protected function generateMessage():void
  {
    $statusString = sprintf(' (user: %s, login-status: %s)', $this->userId, (string)$this->loginStatus);
    $this->message = $this->originalMessage.$statusString;
  }

  /**
   * @param null|string $userId
   *
   * @return LoginException $this
   */
  public function setUserId(?string $userId):LoginException
  {
    $this->userId = $userId;
    $this->generateMessage();
    return $this;
  }

  /**
   * @return null|string
   */
  public function getUserId():?string
  {
    return $this->userId;
  }

  /**
   * @param LoginStatusEnum $status
   *
   * @return LoginException $this
   */
  public function setLoginStatus(LoginStatusEnum $status):LoginException
  {
    $this->loginStatus = $status;
    $this->generateMessage();
    return $this;
  }

  /**
   * @return LoginStatusEnum
   */
  public function getLoginStatus():LoginStatusEnum
  {
    return $this->loginStatus;
  }
}
