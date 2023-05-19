<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021
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

use OCA\Redaxo\Enums\LoginStatusEnum;

class LoginException extends \RuntimeException
{
  /** @var string */
  protected $originalMessage;

  /** @var string|null */
  protected $userId;

  /** @var LoginStatusEnum */
  protected $loginStatus;

  public function __construct(
    string $message
    , int $code = 0
    , \Throwable $previous = null
    , ?string $userId = null
    , ?LoginStatusEnum $loginStatus = null
  ) {
    parent::__construct($this->message, $code, $previous);
    $this->originalMessage = $message;
    $this->userId = $userId;
    $this->loginStatus = $loginStatus?: LoginStatusEnum::UNKNOWN();
    $this->generateMessage();
  }

  protected function generateMessage()
  {
    $statusString = sprintf(' (user: %s, login-status: %s)', $this->userId, (string)$this->loginStatus);
    $this->message = $this->originalMessage.$statusString;
  }

  public function setUserId(?string $userId)
  {
    $this->userId = $userId;
    $this->generateMessage();
  }

  public function getUserId():?string
  {
    return $this->userId;
  }

  public function setLoginStatus(LoginStatusEnum $status)
  {
    $this->loginStatus = $status;
    $this->generateMessage();
  }

  public function getLoginStatus():LoginStatusEnum
  {
    return $this->loginStatus;
  }
}
