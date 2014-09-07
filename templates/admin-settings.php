<?php

/**Embed a Redaxo instance as app into ownCloud, intentionally with
 * single-sign-on.
 * 
 * @author Claus-Justus Heine
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Redaxo\L;
use Redaxo\App;
?>

<div class="section">
  <form id="redaxosettings">
    <legend>
      <img class="redaxologo" src="<?php echo OCP\Util::imagePath(App::APP_NAME, 'redaxo-logo.png'); ?>" >
      <strong><?php echo L::t('Embedded Redaxo CMS');?></strong><br />
    </legend>
    <input type="text"
           name="REX_Location"
           id="REX_Location"
           value="<?php echo $_['redaxolocation']; ?>"
           placeholder="<?php echo L::t('Location');?>"
           title="<?php echo L::t('Please enter the location of the already installed Redaxo
instance. This should either be an abolute path relative to the
root of the owncloud instance, or a complete URL which points to the
web-location of the Redaxo CMS.'); ?>"
    />
    <label for="REX_Location"><?php echo L::t('Redaxo Location');?></label>
    <br/>
    <input type="text"
           name="REX_RefreshInterval"
           id="REX_RefreshInterval"
           value="<?php echo $_['redaxoRefreshInterval']; ?>"
           placeholder="<?php echo L::t('RefreshTime [s]'); ?>"
           title="<?php echo L::t('Please enter the desired session-refresh interval here. The interval is measured in seconds and should be somewhat smaller than the configured session life-time for the Redaxo instance in use.'); ?>"
    />
    <label for="REX_RefreshInterval"><?php echo L::t('Redaxo Session Refresh Interval [s]'); ?></label>
    <br/>        
    <span class="msg"></span>
  </form>
</div>
