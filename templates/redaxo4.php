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

style($appName, 'app');
script($appName, 'app');

?>

<div id="<?php echo $appName; ?>_container" class="<?php echo $cssClass; ?>">
  <img src="<?php echo $urlGenerator->imagePath($appName, 'loader.gif'); ?>" id="<?php echo $appName; ?>Loader" class="<?php echo $cssClass; ?>">
  <div id="<?php echo $appName; ?>FrameWrapper" class="<?php echo $cssClass; ?>">
    <iframe style="overflow:auto"
            src="<?php echo $externalURL.$externalPath;?>"
            id="<?php echo $appName; ?>Frame"
            name="<?php echo $appName; ?>"
            width="100%"
            <?php echo $iframeAttributes; ?>>
    </iframe>
  </div>
</div>
