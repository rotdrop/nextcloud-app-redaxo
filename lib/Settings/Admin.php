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

namespace OCA\Redaxo\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\IDelegatedSettings;
use OCP\IConfig;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\Redaxo\Service\AssetService;
use OCA\Redaxo\Controller\SettingsController;

/** Admin settings. */
class Admin implements IDelegatedSettings
{
  const TEMPLATE = 'admin-settings';
  const ASSET_NAME = 'admin-settings';

  /** @var IConfig */
  private $config;

  /** @var AssetService */
  private $assetService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IConfig $config,
    AssetService $assetService,
  ) {
    $this->appName = $appName;
    $this->config = $config;
    $this->assetService = $assetService;
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /** {@inheritdoc} */
  public function getForm()
  {
    $templateParameters = [
      'appName' => $this->appName,
      'webPrefix' => $this->appName,
      'assets' => [
        Constants::JS => $this->assetService->getJSAsset(self::ASSET_NAME),
        Constants::CSS => $this->assetService->getCSSAsset(self::ASSET_NAME),
      ],
    ];
    foreach (SettingsController::ADMIN_SETTINGS as $setting => $meta) {
      $templateParameters[$setting] = $this->config->getAppValue($this->appName, $setting, $meta['default']);
    }
    return new TemplateResponse(
      $this->appName,
      self::TEMPLATE,
      $templateParameters);
  }

  /** {@inheritdoc} */
  public function getSection()
  {
    return $this->appName;
  }

  /** {@inheritdoc} */
  public function getPriority()
  {
    return 50;
  }

  /** {@inheritdoc} */
  public function getName():?string
  {
    return null;
  }

  /** {@inheritdoc} */
  public function getAuthorizedAppConfig():array
  {
    return [];
  }
}
