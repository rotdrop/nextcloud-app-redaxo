/**Embed a Redaxo CMS instance as app into ownCloud, intentionally
 * with single-sign-on.
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

var Redaxo = Redaxo || {
    appName: 'redaxo',
    refreshInterval: 300,
    refreshTimer: false
};

(function(window, $, Redaxo) {

    Redaxo.routes = function() {
        self = this;
        if (OC.currentUser) {
            var url = OC.generateUrl('apps/'+this.appName+'/refresh');
            this.refresh = function(){
                if (OC.currentUser) {
                    $.post(url, {}, function() {
                        self.refreshTimer = setTimeout(self.refresh, self.refreshInterval*1000);
                    });
                } else if (self.refreshTimer !== false) {
                    clearTimeout(self.refreshTimer);
                    self.refreshTimer = false;
                }
            };
            this.refreshTimer = setTimeout(this.refresh, this.refreshInterval*1000);
        } else if (this.refreshTimer !== false) {
            clearTimeout(this.refreshTimer);
            self.refreshTimer = false;
        }
    };

})(window, jQuery, Redaxo);

$(document).ready(function() {
    Redaxo.routes();
});
