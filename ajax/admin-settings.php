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

namespace Redaxo
{

  $appName = App::APP_NAME;

  \OCP\User::checkAdminUser();
  \OCP\JSON::callCheck();

  if (isset($_POST['REX_Location'])) {
    $location = trim($_POST['REX_Location']);

    if ($location == '') {
      $message = L::t("Got an empty Redaxo location.");  
    } else if (!Util::URLIsValid($location)) {
      $message = L::t("Setting Redaxo location to `%s' but the location seems to be invalid.",
                      array($location));
    } else {
      \OC::$server->getAppConfig()->setValue($appName, 'redaxolocation', $location);
      $message = L::t("Setting Redaxo location to `%s'.", array($location));
    }
  
    \OC_JSON::success(array("data" => array("message" => $message)));

    return true;
  }

  if (isset($_POST['REX_RefreshInterval'])) {
    $refresh = trim($_POST['REX_RefreshInterval']);

    if ($refresh == '') {
      $message = L::t("Got an empty refresh value.");  
    } else if (!is_numeric($refresh)) {
      $message = L::t("This does not appear to be a number: `%s'", array($refresh));
    } else {
      $message = L::t("Setting Redaxo session refresh to %s seconds.", array($refresh));
      \OC::$server->getAppConfig()->setValue($appName, 'refreshInterval', intval($refresh));
    }
  
    \OC_JSON::success(array("data" => array("message" => $message)));

    return true;
  }
  
  \OC_JSON::error(
    array("data" => array("message" => L::t('Unknown request.').print_r($_POST, true))));

  return false;

}
?>
