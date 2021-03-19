<?php
/**
 * Redaxo4Embedded -- Embed Redaxo4 into NextCloud with SSO.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
