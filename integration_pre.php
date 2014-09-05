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

// Ensure that nickname does not exist
$query  = "
SELECT count(*) AS count 
FROM civicrm_contact
WHERE external_identifier LIKE '". $nickname ."-%'
OR source='". $source ."'
";
if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
$results->fetch( );
if ($results->count) {
	echo "Chapter nickname or source already exists, continue (y/n)? ";
	if (trim(fgets(STDIN)) != 'y') exit;
} 

// Process zips
//$pattern = array('/ObjectID,NAME,ST_FIPS,CTY_FIPS,POSTAL/','/.*,.*,.*,.*,(.*)/','/\r/','/\n/');
//$replace = array('','$1,',''.'');
$pattern = array('/CHAPTER NAME,CHAPTER ACRONYM,CHAPTER ZIP CODE,Chapter ID Number/','/.*,.*,(.*),.*/','/\r/','/\n/');
$replace = array('','$1,',''.'');
$zips = trim(preg_replace($pattern, $replace, trim(file_get_contents($zip_territory_file))),',');
if ($debug) echo "$zips\n";

// Pre-integration numbers
$query  = "SELECT DISTINCT count(*) as count 
FROM civicrm_contact AS c 
LEFT JOIN civicrm_address AS a ON c.id=a.contact_id AND a.is_primary=1 
WHERE a.postal_code IN (" . $zips . ") 
";
if ($debug) echo "$query";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$contact_count = $results->count;
	$message .= "Pre-integration contacts: $results->count\n";
}

$query  = "SELECT DISTINCT count(*) as count 
FROM civicrm_contact AS c 
LEFT JOIN civicrm_address AS a ON c.id=a.contact_id AND a.is_primary=1 
LEFT JOIN civicrm_membership AS m ON c.id=m.contact_id 
WHERE a.postal_code IN (" . $zips . ") 
AND m.status_id IN (1,2,3) 
AND m.membership_type_id IN (1,5)
";
if ($debug) echo "$query";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$member_count = $results->count;
	$message .=  "Pre-integration new/current/lapsed members: $results->count\n";
}

$query  = "SELECT DISTINCT count(*) as count 
FROM civicrm_contact AS c 
LEFT JOIN civicrm_address AS a ON c.id=a.contact_id AND a.is_primary=1 
LEFT JOIN civicrm_membership AS m ON c.id=m.contact_id 
WHERE a.postal_code IN (" . $zips . ") 
AND m.status_id=4
AND m.membership_type_id IN (1,5)
";
if ($debug) echo "$query";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$expired_count = $results->count;
	$message .=  "Pre-integration expired members: $results->count\n";
}

// Output results
if ($email) mail($recipient, $subject . " (pre)", $message);
echo "$message

Next steps:

– Update the \"contact_id\" column by prepending values with \"$nickname-\" in contact file
– Duplicate the \"contact_id\" column and append as the last column in contact file
– Import contacts using CiviCRM > Contacts > Import Contacts 
– Import memberships using CiviCRM > Memberships > Import Members
– Make sure current working directory is writable by mysql
– Run script: \n\n\tphp /usr/local/bin/imba/integration_post.php $argv[1]\n\n


The following zipcodes are in this chapter's territory: $zips\n";

// Write stats to file
$outfile = getcwd() . "/pre-integrations_stats-$nickname-" .date('Ymd-U') . ".txt";
$fh = fopen($outfile, 'w') or die("\nCan't open file $outfile");
fwrite($fh, $message);
fclose($fh);

// Run integrate_post
/*
echo "Run integrate_post.php now? ";
if (trim(fgets(STDIN)) != 'y') exit;
exec('/usr/bin/php /usr/local/bin/imba/integration_post.php ' . $argv[1]);
*/
?>
