<?php

/**Auth module for this app.
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

/**Redaxo namespace to prevent name-collisions.
 */
namespace Redaxo 
{

/**This class provides the two static login- and logoff-hooks needed
 * for authentication without storing passwords verbatim.
 */
class AuthHooks
{
  public static function login($params)
  {
    if (defined('REDAXO_INCLUDE')) {
      return;
    }

    $redaxoLocation = \OCP\Config::GetAppValue(App::APP_NAME, 'redaxolocation', '');

    $redaxo = new App($redaxoLocation);
    $redaxoURL = $redaxo->redaxoURL();

    $username = $params['uid'];
    $password = $params['password'];

    if ($redaxo->login($username, $password)) {
      $redaxo->emitAuthHeaders();
      \OCP\Util::writeLog(App::APP_NAME,
                          "Redaxo login of user ".
                          $username.
                          " probably succeeded.",
                          \OC_Log::INFO);
    } else {
      \OCP\Util::writeLog(App::APP_NAME,
                          "Redaxo login of user ".
                          $username.
                          " probably failed.",
                          \OC_Log::INFO);
    }
  }
  
  public static function logout()
  {
    if (defined('REDAXO_INCLUDE')) {
      return;
    }

    $redaxoLocation = \OCP\Config::GetAppValue(App::APP_NAME, 'redaxolocation', '');
    $redaxo = new App($redaxoLocation);
    if ($redaxo->logout()) {
      $redaxo->emitAuthHeaders();
      \OCP\Util::writeLog(App::APP_NAME,
                          "Redaxo logoff probably succeeded.",
                          \OC_Log::INFO);
    }
  }

  /**Try to refresh the DW session by fetching the DW version via
   * XMLRPC.
   */
  public static function refresh() 
  {
    if (defined('REDAXO_INCLUDE')) {
      return;
    }

    $redaxoLocation = \OCP\Config::GetAppValue(App::APP_NAME, 'redaxolocation', '');
    $redaxo = new App($redaxoLocation);
    if (!$redaxo->isLoggedIn()) { // triggers load of Redaxo start page   
        \OCP\Util::writeLog(App::APP_NAME,
                            "Redaxo refresh failed.",
                            \OC_Log::ERROR);
    } else {
        \OCP\Util::writeLog(App::APP_NAME,
                            "Redaxo refresh probably succeeded.",
                            \OC_Log::INFO);
    }
  }
  

};

} // namespace

?>
