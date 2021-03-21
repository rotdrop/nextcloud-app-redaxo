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
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\ILogger;
use OCP\IL10N;

use OCA\Redaxo4Embedded\Settings\Admin;
use OCA\Redaxo4Embedded\Service\AuthRedaxo4 as Authenticator;

class AdminSettingsController extends Controller
{
  use \OCA\Redaxo4Embedded\Traits\LoggerTrait;
  use \OCA\Redaxo4Embedded\Traits\ResponseTrait;

  private $userId;

  private $config;

  private $urlGenerator;

  public function __construct(
    $appName
    , IRequest $request
    , Authenticator $authenticator
    , IConfig $config
    , IURLGenerator $urlGenerator
    , $UserId
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);

    $this->authenticator = $authenticator;

    $this->config = $config;
    $this->urlGenerator = $urlGenerator;

    $this->userId = $UserId;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  public function set()
  {
    foreach (array_keys(Admin::SETTINGS) as $setting) {
      if (!isset($this->request[$setting])) {
        continue;
      }
      $value = trim($this->request[$setting]);
      $this->logDebug("Got setting ".$setting.": ".$value);
      switch ($setting) {
        case 'externalLocation':
          if ($value === '') { // ok, reset
            break;
          }
          if ($value[0] == '/') {
            $value = $this->urlGenerator->getAbsoluteURL($value);
          }
          $urlParts = parse_url($value);
          if (empty($urlParts['scheme']) || !preg_match('/https?/', $urlParts['scheme'])) {
            return self::grumble($this->l->t("Scheme of external URL must be one of `http' or `https', `%s' given.", [$urlParts['scheme']]));
          }
          if (empty($urlParts['host'])) {
            return self::grumble($this->l->t("Host-part of external URL seems to be empty"));
          }
          $this->authenticator->externalURL($value);
          if ($this->authenticator->loginStatus() == Authenticator::STATUS_UNKNOWN) {
            return self::grumble($this->l->t("Redaxo4 instance does not seem to be reachable at %s", [ $value ]));
          }
          break;
        case 'authenticationRefreshInterval':
          $realValue = filter_var($value, FILTER_VALIDATE_INT, ['min_range' => 0]);
          if ($realValue === false) {
            return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not in the allowed range.', [$value, $setting]));
          }
          $value = $realValue;
          break;
        case 'enableSSLVerify':
          $realValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
          if ($realValue === null) {
            return self::grumble($this->l->t('Value "%1$s" for set "%2$s" is not convertible to boolean.', [$value, $setting]));
          }
          $value = $realValue;
          $strValue = $value ? 'on' : 'off';
          break;
      }
      $this->config->setAppValue($this->appName, $setting, $value);
      if (empty($strValue)) {
        $strValue = $value;
      }
      return new DataResponse([
        'value' => $value,
        'message' => $this->l->t("Parameter %s set to %s", [ $setting, $strValue ]),
      ]);
    }
    return new DataResponse([ 'message' => $this->l->t('Unknown Request') ], Http::STATUS_BAD_REQUEST);
  }
}
