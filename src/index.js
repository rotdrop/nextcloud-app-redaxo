/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023
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

import { webPrefix } from './config.js';
import { loadHandler } from './redaxo.js';
import '../style/redaxo.css';

const jQuery = require('jquery');
const $ = jQuery;

$(function() {

  console.info('Redaxo webPrefix', webPrefix);
  const container = $('#' + webPrefix + '_container');
  const frameWrapper = $('#' + webPrefix + 'FrameWrapper');
  const frame = $('#' + webPrefix + 'Frame');
  const contents = frame.contents();

  const setHeightCallback = function() {
    container.height($('#content').height());
  };

  if (frame.length > 0) {
    frame.on('load', function() {
      console.info(frameWrapper);
      loadHandler($(this), frameWrapper, setHeightCallback);
    });

    let resizeTimer;
    $(window).resize(function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(setHeightCallback);
    });
  }
  if (contents.find('a.rex-logout').length > 0) {
    loadHandler(frame, frameWrapper, setHeightCallback);
  }

});
