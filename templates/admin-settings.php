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

namespace OCA\Redaxo;

script($appName, 'admin-settings');

?>

<div class="section">
  <h2><?php p($l->t('Embedded Redaxo')) ?></h2>
  <form id="<?php p($webPrefix); ?>settings">
    <input type="text"
           name="externalLocation"
           id="externalLocation"
           class="externalLocation"
           value="<?php echo $externalLocation; ?>"
           placeholder="<?php echo $l->t('Location');?>"
           title="<?php echo $l->t('Please enter the location of the already installed Redaxo
instance. This should either be an abolute path relative to the
root of the web-browser, or a complete URL which points to the
web-location of the Redaxo. In order to make things work your
have to enable the XMLRPC protocol in your Redaxo.'); ?>"
    />
    <label for="externalLocation"><?php echo $l->t('Redaxo Location');?></label>
    <br/>
    <input type="number"
           min="0"
           name="authenticationRefreshInterval"
           id="authenticationRefreshInterval"
           class="authenticationRefreshInterval"
           value="<?php echo $authenticationRefreshInterval; ?>"
           placeholder="<?php echo $l->t('Refresh Time [s]'); ?>"
           title="<?php echo $l->t('Please enter the desired session-refresh interval here. The interval is measured in seconds and should be somewhat smaller than the configured session life-time for the Redaxo instance in use.'); ?>"
    />
    <label for="authenticationRefreshInterval"><?php echo $l->t('Redaxo Session Refresh Interval [s]'); ?></label>
    <br/>
    <input type="number"
           min="0"
           name="reloginDelay"
           id="reloginDelay"
           class="reloginDelay"
           value="<?php echo $reloginDelay; ?>"
           placeholder="<?php echo $l->t('delay [s]'); ?>"
           title="<?php echo $l->t('Please enter the relogin delay. The delay is measured in seconds and must be somewhat larger than the configured relogin delay of the Redaxo instance in use.'); ?>"
    />
    <label for="reloginDelay"><?php echo $l->t('Redaxo Relogin Delay [s]'); ?></label>
    <br/>
    <input type="checkbox"
           name="enableSSLVerify"
           id="enableSSLVerify"
           class="checkbox"
	   <?php if ($enableSSLVerify) { echo 'checked="checked"'; } ?>
    />
    <label title="<?php p($l->t('Disable SSL verification, e.g. for self-signed certification or known mis-matching host-names like "localhost".')); ?>"
           for="enableSSLVerify">
      <?php p($l->t('Enable SSL verification.')); ?>
    </label>
    <br/>
    <span class="msg"></span>
  </form>
</div>
