<?php
/**
 * Redaxo - Owncloud redaxo plugin
 *
 * @author Claus-Justus Heine
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Embed a Redaxo instance into an ownCloud instance by means of an
 * iframe (or maybe slightly more up-to-date: an object) tag.
 *
 * This was inspired by the Roundcube plugin by Martin Reinhardt and
 * David Jaedke, but as that one stores passwords -- and even in a
 * data-base -- and even more or less unencrypted, this is now a
 * complete rewrite.
 *
 * We implement part-of a single-sign-on strategy: when the user logs
 * into the ownCloud instance, we execute a login-hook (which has the
 * passphrase or other credentials readily available) and log into the
 * Redaxo instance by means of their xmlrpc protocol. The cookies
 * returned by the Redaxo instance are then simply forwarded the
 * web-browser of the user. Not password information is stored on the
 * host. So this should be as secure or insecure as Redaxo behaves
 * itself.
 *
 */

$appName = 'redaxo';

$l = new OC_L10N($appName);

OC::$CLASSPATH['Redaxo\L'] = OC_App::getAppPath($appName) . '/lib/util.php';
OC::$CLASSPATH['Redaxo\Util'] = OC_App::getAppPath($appName) . '/lib/util.php';
OC::$CLASSPATH['Redaxo\App'] = OC_App::getAppPath($appName) . '/lib/redaxo.php';
OC::$CLASSPATH['Redaxo\AuthHooks'] = OC_App::getAppPath($appName) . '/lib/auth.php';
OC::$CLASSPATH['Redaxo\Config'] = OC_App::getAppPath($appName) . '/lib/config.php';
OC::$CLASSPATH['Redaxo\RPC'] = OC_App::getAppPath($appName) . '/lib/remoteprotocol.php';

OCP\Util::connectHook('OC_User', 'post_login', 'Redaxo\AuthHooks', 'login');
OCP\Util::connectHook('OC_User', 'logout', 'Redaxo\AuthHooks', 'logout');

// Hurray! There is a config hook!
OCP\Util::connectHook('\OCP\Config', 'js', 'Redaxo\Config', 'jsLoadHook');

OCP\App::registerAdmin($appName, 'admin-settings');

// Add global JS routines; this one triggers a session refresh for DW.
OCP\Util::addScript($appName, 'routes');

OCP\App::addNavigationEntry(array(
		'id' => $appName, 
		'order' => 10, 
                'href' => OCP\Util::linkToRoute('redaxo_root'),
		'icon' => OCP\Util::imagePath($appName, 'redaxo-logo-gray.png'),
		'name' => $l->t('Redaxo')));

?>
