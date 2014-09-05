<?php
// Require config file from command line argument
if (count($argv) > 1) {
	require_once $argv[1];
} else {
	echo "usage: $argv[0] [config_script].php\n";
	exit;
}

// Initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton();
require_once "api/v2/Contact.php";

// Process zips
$pattern = array('/ObjectID,NAME,ST_FIPS,CTY_FIPS,POSTAL/','/.*,.*,.*,.*,(.*)/','/\r/','/\n/');
$replace = array('','$1,',''.'');
$zips = trim(preg_replace($pattern, $replace, trim(file_get_contents($zip_territory_file))),',');
if ($debug) echo "$zips\n";

// Post-integration numbers
$query  = "SELECT DISTINCT count(*) as count 
FROM civicrm_contact AS c 
LEFT JOIN civicrm_address AS a ON c.id=a.contact_id AND a.is_primary=1 
WHERE (a.postal_code IN (" . $zips . ") 
OR c.source='" . $source . "')
";
if ($debug) echo "$query";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$contact_count = $results->count;
	$message .= "Contacts: $results->count\n";
}

$query  = "SELECT DISTINCT count(*) as count 
FROM civicrm_contact AS c 
LEFT JOIN civicrm_address AS a ON c.id=a.contact_id AND a.is_primary=1 
LEFT JOIN civicrm_membership AS m ON c.id=m.contact_id 
WHERE (a.postal_code IN (" . $zips . ") 
OR c.source='" . $source . "')
AND m.status_id IN (1,2,3) 
AND m.membership_type_id IN (1,5)
";
if ($debug) echo "$query";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$member_count = $results->count;
	$message .=  "New/current/lapsed members: $results->count\n";
}

$query  = "SELECT DISTINCT count(*) as count 
FROM civicrm_contact AS c 
LEFT JOIN civicrm_address AS a ON c.id=a.contact_id AND a.is_primary=1 
LEFT JOIN civicrm_membership AS m ON c.id=m.contact_id 
WHERE (a.postal_code IN (" . $zips . ") 
OR c.source='" . $source . "')
AND m.status_id=4
AND m.membership_type_id IN (1,5)
";
if ($debug) echo "$query";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$expired_count = $results->count;
	$message .=  "Expired members: $results->count\n";
}

// Output results
if ($email) mail($recipient, "Integration stats", $message);
echo "$message\n";

// Write stats to file
/*
$outfile = getcwd() . "/post-integrations_stats-$nickname-" .date('Ymd-U') . ".txt";
$fh = fopen($outfile, 'w') or die("\nCan't open file $outfile");
fwrite($fh, $message);
fclose($fh);
*/
?>