/**
 * Embed a Redaxo4 instance as app into NextCloud, intentionally with
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
    console.info("State", state);
    Redaxo4Embedded = $.extend({}, state);
    Redaxo4Embedded.refreshTimer = false;
}

Redaxo4Embedded.Settings = Redaxo4Embedded.Settings || {};

(function(window, $, Redaxo4Embedded) {

    /**
     * Fetch data from an error response.
     *
     * @param xhr jqXHR, see fail() method of jQuery ajax.
     *
     * @param status from jQuery, see fail() method of jQuery ajax.
     *
     * @param errorThrown, see fail() method of jQuery ajax.
     */
    Redaxo4Embedded.ajaxFailData = function(xhr, status, errorThrown) {
        const ct = xhr.getResponseHeader("content-type") || "";
        var data = {
            'error': errorThrown,
            'status': status,
            'message': t(Redaxo4Embedded.appName, 'Unknown JSON error response to AJAX call: {status} / {error}')
  };
        if (ct.indexOf('html') > -1) {
            console.debug('html response', xhr, status, errorThrown);
            console.debug(xhr.status);
            data.message = t(Redaxo4Embedded.appName, 'HTTP error response to AJAX call: {code} / {error}',
                             {'code': xhr.status, 'error': errorThrown});
        } else if (ct.indexOf('json') > -1) {
            const response = JSON.parse(xhr.responseText);
            //console.info('XHR response text', xhr.responseText);
            //console.log('JSON response', response);
            data = {...data, ...response };
        } else {
            console.log('unknown response');
        }
        //console.info(data);
        return data;
    };

    Redaxo4Embedded.Settings.storeSettings = function(event, id) {
        const webPrefix = Redaxo4Embedded.webPrefix;
        const msg = $('#'+webPrefix+'settings .msg');
        if ($.trim(msg.html()) == '') {
            msg.hide();
        }
	const post = $(id).serialize();
	$.post(OC.generateUrl('/apps/'+Redaxo4Embedded.appName+'/settings/admin/set'), post)
            .done(function(data) {
                console.info("Got response data", data);
                if (data.value) {
                    $(id).val(data.value);
                }
		if (data.message) {
	            msg.html(data.message);
		    msg.show();
		}
	    })
            .fail(function(xhr, status, errorThrown) {
                const response = Redaxo4Embedded.ajaxFailData(xhr, status, errorThrown);
                console.error(response);
                if (response.message) {
	            msg.html(response.message);
                    msg.show();
                }
            });
        return false;
    };

})(window, jQuery, Redaxo4Embedded);


$(function(){

    $('#externalLocation').blur(function (event) {
        event.preventDefault();
        Redaxo4Embedded.Settings.storeSettings(event, '#externalLocation');
        return false;
    });

    $('#authenticationRefreshInterval').blur(function (event) {
        event.preventDefault();
        Redaxo4Embedded.Settings.storeSettings(event, '#authenticationRefreshInterval');
        return false;
    });
});
