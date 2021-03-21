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

import { webPrefix } from './config.js';

const jQuery = require('jquery');
const $ = jQuery;

const loadHandler = function(frame, frameWrapper, callback) {
  const contents = frame.contents();

  // Remove the logout stuff
  contents.find('ul.rex-logout').remove();

  // shift the entire thing a little bit into the inside
  contents.find('div#rex-website').css({
    'margin-left': '50px',
    'margin-top': '50px',
  });

  // Make sure all external links are opened in another window
  contents.find('a').filter(function() {
    return this.hostname && this.hostname !== window.location.hostname;
  }).each(function() {
    $(this).attr('target','_blank');
  });

  if (typeof callback == 'undefined') {
    callback = function() {};
  }

  const loader = $('#' + webPrefix + 'Loader');
  console.info('loader', loader);
  if (frameWrapper.is(':hidden')) {
    console.info('hide slideDown');
    loader.fadeOut('slow', function() {
      console.info('fade out callback');
      frameWrapper.slideDown('slow', function() {
        console.info('slideDown callback');
        callback(frame, frameWrapper);
      });
    });
  } else {
    console.info('just display');
    loader.fadeOut('slow');
    callback(frame, frameWrapper);
  }
};

export { loadHandler };

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***
