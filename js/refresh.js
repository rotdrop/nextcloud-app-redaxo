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
    console.info("Redaxo4Embedded", Redaxo4Embedded);
}

(function(window, $, Redaxo4Embedded) {

    Redaxo4Embedded.refresh = function() {
        const self = this;
        if (!(Redaxo4Embedded.refreshInterval >= 30)) {
            console.error("Refresh interval too short", Redaxo4Embedded.refreshInterval);
            Redaxo4Embedded.refreshInterval = 30;
        }
        if (OC.currentUser) {
            const url = OC.generateUrl('apps/'+this.appName+'/authentication/refresh');
            this.refresh = function(){
                if (OC.currentUser) {
                    $.post(url, {}).always(function () {
                        console.info('Redaxo4 refresh scheduled', self.refreshInterval * 1000);
                        self.refreshTimer = setTimeout(self.refresh, self.refreshInterval*1000);
                    });
                } else if (self.refreshTimer !== false) {
                    clearTimeout(self.refreshTimer);
                    self.refreshTimer = false;
                }
            };
            console.info('Redaxo4 refresh scheduled', this.refreshInterval * 1000);
            this.refreshTimer = setTimeout(this.refresh, this.refreshInterval*1000);
        } else if (this.refreshTimer !== false) {
            console.info('OC.currentUser appears unset');
            clearTimeout(this.refreshTimer);
            self.refreshTimer = false;
        }
    };

})(window, jQuery, Redaxo4Embedded);

$(function() {
    console.info('Starting Redaxo4 refresh');
    Redaxo4Embedded.refresh();
});
