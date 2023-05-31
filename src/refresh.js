/**
 * Redaxo -- a Nextcloud App for embedding Redaxo.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2020, 2021, 2023, 2023
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

import { getCurrentUser } from '@nextcloud/auth';
import axios from '@nextcloud/axios';
import onDocumentLoaded from './toolkit/util/on-document-loaded.js';
import generateUrl from './toolkit/util/generate-url.js';
import { getInitialState } from './toolkit/services/InitialStateService.js';

const state = getInitialState();
let refreshInterval = state.authenticationRefreshInterval;

if (!(refreshInterval >= 30)) {
  console.error('Refresh interval too short', refreshInterval);
  refreshInterval = 30;
}

let refreshTimer = null;
const url = generateUrl('authentication/refresh');

const refreshHandler = async function() {
  await axios.post(url);
  console.info('Redaxo refresh scheduled', refreshInterval * 1000);
  refreshTimer = setTimeout(refreshHandler, refreshInterval * 1000);
};

onDocumentLoaded(() => {
  if (getCurrentUser()) {
    console.info('Starting Redaxo authentication refresh.');
    refreshTimer = setTimeout(refreshHandler, refreshInterval * 1000);
  } else {
    console.info('cloud-user appears unset.');
    clearTimeout(refreshTimer);
    refreshTimer = false;
  }
});
