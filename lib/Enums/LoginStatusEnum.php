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

namespace OCA\Redaxo4Embedded\Enums;

/**
 * Login status enum.
 *
 * @method static LoginStatusEnum UNKNOWN()
 * @method static LoginStatusEnum LOGGED_IN()
 * @method static LoginStatusEnum LOGGED_OUT()
 */
final class LoginStatusEnum extends \MyCLabs\Enum\Enum
{
  private const UNKNOWN = 'unknown';
  private const LOGGED_IN = 'logged-in';
  private const LOGGED_OUT = 'logged-out';
}
