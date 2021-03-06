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


// Backup table
$table_name = "civicrm_value_region_and_chapter_12";
$outfile = getcwd() . "/$table_name-$nickname-" .date('Ymd-U') . ".sql";
$query  = "
SELECT * INTO OUTFILE '$outfile' FROM $table_name
";
if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
if (!is_file($outfile)) {
	echo "Error reading $outfile";
	exit;
} 
echo "\nBackup writen to: $outfile.\n";

// Delete empty and NULL chapters
echo "Continue and delete empty and NULL chapters (y/n)? ";
if (trim(fgets(STDIN)) != 'y') exit;
$query  = "
DELETE FROM civicrm_value_region_and_chapter_12 
WHERE entity_id IN (
	SELECT DISTINCT c.id 
	FROM civicrm_contact AS c 
	WHERE c.source='" . $source . "'
) 
AND (
	chapter_80 IN ('At Large','Unassigned','') 
	OR chapter_80 IS NULL
)
";
if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	echo $results->count . " " . $results->chapter . " " . $results->sort_name . "\n";
}

// Find updated region/chapters for reference
if ($verbose) {
	$query  = "
	SELECT count(*) AS count, c.sort_name AS sort_name, r.chapter_80 AS chapter 
	FROM civicrm_contact AS c 
	LEFT OUTER JOIN civicrm_value_region_and_chapter_12 AS r ON c.id = r.entity_id 
	WHERE c.id IN (
		SELECT DISTINCT c.id 
		FROM civicrm_contact AS c 
		WHERE c.source='" . $source . "'
	) 
	GROUP BY r.chapter_80
	";
	if ($debug) echo "$query\n";
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	echo "
";
	while ( $results->fetch( ) ) {
		echo $results->count . " " . $results->chapter . " " . $results->sort_name . "\n";
	}
	echo "\nContinue (y/n)? ";
	if (trim(fgets(STDIN)) != 'y') exit;
}

// Create and a region/chapter insert statement file for contacts
$outfile = getcwd() . "/region-chapter_insert-$nickname-" .date('Ymd-U') . ".sql";
$query  = "
SELECT DISTINCT CONCAT('insert into civicrm_value_region_and_chapter_12 (entity_id,region_79,chapter_80) values (',c.id,',\'". $region. "\',CONCAT(char(01),\'". $chapter. "\',char(01))) ON DUPLICATE KEY UPDATE region_79=\'". $region . "\',chapter_80=CONCAT(SUBSTRING(chapter_80, 1, LENGTH(chapter_80)-1), CONCAT(char(01),\'". $chapter. "\',char(01)));') FROM civicrm_contact AS c LEFT OUTER JOIN civicrm_value_region_and_chapter_12 AS r ON c.id = r.entity_id WHERE (r.chapter_80 NOT LIKE '%". $chapter. "%' OR r.chapter_80 IS NULL) AND c.id IN (SELECT DISTINCT c.id FROM civicrm_contact AS c WHERE c.source='". $source. "') INTO OUTFILE '" . $outfile ."';
";
if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
if (!is_file($outfile)) {
	echo "Error reading $outfile";
	exit;
} 
echo "\nRegion/chaper insert writen to: $outfile.\nWould you like to view the file (y/n)? ";
if (trim(fgets(STDIN)) == 'y') {
	system('/bin/cat -n ' . $outfile);
}

// Import region/chapter insert file
echo "\nContinue and import update file (y/n)? ";
if (trim(fgets(STDIN)) != 'y') exit;
system('mysql -uroot -pAjefIlAfJi2 imba_civicrm < \'' . $outfile . '\'');
echo "\n$outfile imported.\n";

echo "Contacts tagged with $chapter";
?>