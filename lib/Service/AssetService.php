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

namespace OCA\Redaxo\Service;

use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\Redaxo\Constants;

/**
 * Return JavaScript- and CSS-assets names dealing with the attached content
 * hashes
 */
class AssetService
{
  use \OCA\Redaxo\Toolkit\Traits\AssetTrait {
    getAsset as public;
    getJSAsset as public;
    getCSSAsset as public;
  }

  const JS = Constants::JS;
  const CSS = Constants::CSS;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IL10N $l10n, LoggerInterface $logger)
  {
    $this->logger = $logger;
    $this->l = $l10n;
    $this->initializeAssets(__DIR__);
  }
  // phpcs:enable
}
