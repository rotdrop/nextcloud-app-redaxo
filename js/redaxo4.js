/**
 * Embed a Redaxo4 instance as app into ownCloud, intentionally with
 * single-sign-on.
 *
 * @author Claus-Justus Heine
 * @copyright 2013-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

var Redaxo4Embedded = Redaxo4Embedded || {};
if (!Redaxo4Embedded.appName) {
    const state = OCP.InitialState.loadState('redaxo4embedded', 'initial');
    Redaxo4Embedded = $.extend({}, state);
    Redaxo4Embedded.refreshTimer = false;
};

(function(window, $, Redaxo4Embedded) {

  Redaxo4Embedded.loadHandler = function(frame, frameWrapper, callback) {
    const contents = frame.contents();
    const webPrefix = Redaxo4Embedded.webPrefix;

    console.info('frame', frame);
    console.info('frameWrapper', frameWrapper);

    // Remove the logout stuff
    contents.find('ul.rex-logout').remove();

    // shift the entire thing a little bit into the inside
    contents.find('div#rex-website').css({'margin-left': '50px',
                                          'margin-top': '50px'});

    // Make sure all external links are opened in another window
    contents.find('a').filter(function() {
      return this.hostname && this.hostname !== window.location.hostname;
    }).each(function() {
      $(this).attr('target','_blank');
    });

    if (typeof callback == 'undefined') {
      callback = function() {};
    }

    const loader = $('#'+webPrefix+'Loader');
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
  }

})(window, jQuery, Redaxo4Embedded);

$(function() {

  const webPrefix = Redaxo4Embedded.webPrefix;
  console.info('webPrefix', webPrefix);
  const container = $('#'+webPrefix+'_container');
  const frameWrapper = $('#'+webPrefix+'FrameWrapper');
  const frame = $('#'+webPrefix+'Frame');
  const contents = frame.contents();

  const setHeightCallback = function() {
    container.height($('#content').height());
  };

  if (frame.length > 0) {
    frame.load(function() {
      console.info(frameWrapper);
      Redaxo4Embedded.loadHandler($(this), frameWrapper, setHeightCallback);
    });

    var resizeTimer;
    $(window).resize(function()  {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(setHeightCallback);
    });
  }
  if (contents.find('ul.rex-logout').length > 0) {
    Redaxo4Embedded.loadHandler(frame, frameWrapper, setHeightCallback);
  }

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***
