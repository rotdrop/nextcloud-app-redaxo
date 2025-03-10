<?php
/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023, 2025
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

namespace OCA\Redaxo\Settings;

use OCP\Settings\IIconSection;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

/** Admin settings section. */
class AdminSection implements IIconSection
{
  use \OCA\Redaxo\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected string $appName,
    protected IURLGenerator $urlGenerator,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /** {@inheritdoc} */
  public function getID()
  {
    return $this->appName;
  }

  /** {@inheritdoc} */
  public function getName()
  {
    // @@TODO make this configurable
    return $this->l->t("Redaxo Integration");
  }

  /** {@inheritdoc} */
  public function getPriority()
  {
    return 50;
  }

  /** {@inheritdoc} */
  public function getIcon()
  {
    // @@TODO make it configurable
    return $this->urlGenerator->imagePath($this->appName, 'app.svg');
  }
}
