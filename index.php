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

use Redaxo\App;
use Redaxo\Util;

$appName = App::APP_NAME;

// Check if we are a user
OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled($appName);

// Check if we are a user
if (!OCP\User::isLoggedIn()) {
  header("Location: " . OCP\Util::linkTo('', 'index.php' ));
  exit();
}

// Load our style
OCP\Util::addStyle($appName, $appName);

// add needed JS
OCP\Util::addScript($appName, $appName);

// add new navigation entry
OCP\App::setActiveNavigationEntry($appName);

$redaxoLocation = OCP\Config::GetAppValue($appName, 'redaxolocation', '');

$tmpl = new OCP\Template($appName, "redaxo", "user");

$redaxo = new App($redaxoLocation);
$redaxoURL  = $redaxo->redaxoURL();
$redaxoPath = Util::cgiValue('redaxoPath', '');

$redaxo->emitAuthHeaders();

$tmpl->assign('app', $appName);
$tmpl->assign('redaxolocation', $redaxoLocation);
$tmpl->assign('redaxoURL', $redaxoURL);
$tmpl->assign('redaxoPath', $redaxoPath);

$tmpl->printpage();

?>
