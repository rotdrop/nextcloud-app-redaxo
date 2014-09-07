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

Redaxo.Settings = Redaxo.Settings || {};

(function(window, $, Redaxo) {
    Redaxo.Settings.storeSettings = function(event, id) {
	event.preventDefault();
        if ($.trim($('#redaxosettings .msg').html()) == '') {
            $('#redaxosettings .msg').hide();
        }
	var post = $(id).serialize();
	$.post(OC.filePath(Redaxo.appName, 'ajax', 'admin-settings.php'),
               post,
               function(data){
                   if (data.status == 'success') {
	               $('#redaxosettings .msg').html(data.data.message);
                   } else {
	               $('#redaxosettings .msg').html(data.data.message);
                   }
                   $('#redaxosettings .msg').show();
	       }, 'json');
    };

})(window, jQuery, Redaxo);


$(document).ready(function(){

    $('#REX_Location').blur(function (event) {
        event.preventDefault();
        Redaxo.Settings.storeSettings(event, '#REX_Location');
        return false;
    });

    $('#REX_RefreshInterval').blur(function (event) {
        event.preventDefault();
        Redaxo.Settings.storeSettings(event, '#REX_RefreshInterval');
        return false;
    });
});
