<?php

OC::$CLASSPATH['Redaxo\AuthHooks'] = OC_App::getAppPath("redaxo") . '/lib/auth.php';

$this->create('redaxorefresh', '/refresh')->post()->action('Redaxo\AuthHooks', 'refresh');

?>
