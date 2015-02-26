<?php

$this->create('redaxo_root', '/')
  ->actionInclude('redaxo/index.php');

$this->create('redaxo_index', 'index.php')
  ->actionInclude('redaxo/index.php');

$this->create('redaxo_ajax_admin_settings', 'ajax/admin-settings.php')
  ->actionInclude('redaxo/ajax/admin-settings.php');

OC::$CLASSPATH['Redaxo\AuthHooks'] = OC_App::getAppPath("redaxo") . '/lib/auth.php';

$this->create('redaxorefresh', '/refresh')->post()->action('Redaxo\AuthHooks', 'refresh');

?>
