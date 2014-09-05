<?php
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php';
require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton();

$groups = civicrm_api('group','get', array('version' => 3));
foreach ($groups['values'] as $group){
	echo("id: " . $group['id']);
	$contacts = civicrm_api('contact', 'get', array('version' => 3, 'group' => $group['id']));
	echo(" is_error: ". $contacts['is_error'] . " count: " . $contacts['count'] . "\n");
}

?>
