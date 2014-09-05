<?php
// initialize script
$debug = 0;
$verbose = 1;
$revenue_share_table = 'civicrm_value_revenue_sharing_11';
//$revenue_share_table = 'civicrm_value_revenue_sharing_test';
require_once '/usr/local/bin/imba/includes/functions.inc.php';

// initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton();
require_once "api/v2/Contact.php";

// get chapters
$chapters = get_chapters();
if (!$chapters) exit;
if ($debug) print_r($chapters);

/*
// update contributions with chapter but "None" for region
foreach ($chapters as $chapter => $region) {
	$query  = "
	UPDATE " . $revenue_share_table . " 
	SET region_76 = '" . $region. "'
	WHERE region_76 = 'None'
	AND chapter_77 = '" . $chapter . "'
	";
	if ($debug) echo "$query\n";
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
}
*/

// get regions and territories
$query  = "
SELECT c.id, c.display_name, r.postal_code_prefix_territory_172
FROM civicrm_contact AS c, civicrm_value_region_information_23 AS r
WHERE c.id = r.entity_id 
AND c.contact_type = 'Organization'
AND c.contact_sub_type = 'Region'
";
if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );

// process prefixes for mysql regex
while ( $results->fetch( ) ) {
	$pattern = array('/^/','/,/');
	$replace = array('^','|^');
	$prefixes = preg_replace($pattern, $replace, trim($results->postal_code_prefix_territory_172));
	if ($debug) echo $prefixes . "\n";
	if ($debug) echo $results->postal_code_prefix_territory_172 . "\n";
	
	
	// find contributions without region within prefix
	if ($verbose) {
		$query  = "
		SELECT count(*) as total, sum(t.total_amount) as sum, s.chapter_77, s.region_76
		FROM civicrm_contribution AS t, " . $revenue_share_table . " AS s, civicrm_address AS a
		WHERE t.id = s.entity_id
		AND t.contact_id = a.contact_id
		AND a.is_primary = 1 
		AND t.contribution_type_id = 28
		AND t.contribution_status_id = 1
		AND (s.chapter_77 = 'Unassigned' OR s.chapter_77 = 'At Large')
		AND (s.region_76 = 'None' OR s.region_76 is NULL OR s.region_76 = '')
		AND a.postal_code REGEXP '" . $prefixes . "'
		GROUP BY s.chapter_77, s.region_76
		";
		if ($debug) echo "$query\n";
		$params = array( );
		$summary =& CRM_Core_DAO::executeQuery( $query, $params );
		echo  "Total\tSum\tChapter\tRegion\n" ;
		while ( $summary->fetch( ) ) {
			echo $results->display_name . "\t" . $summary->total . "\t" .$summary->sum. "\t" .$summary->chapter_77. "\t" .$summary->region_76. "\n" ;
		}
	}
	// update contributions without regions within prefixes
	$query  = "
	UPDATE civicrm_contribution AS t, " . $revenue_share_table . " AS s, civicrm_address AS a
	SET  s.region_76 = '" . $results->display_name . "'
	WHERE t.id = s.entity_id
	AND t.contact_id = a.contact_id
	AND a.is_primary = 1 
	AND t.contribution_type_id = 28
	AND (s.chapter_77 = 'Unassigned' OR s.chapter_77 = 'At Large')
	AND (s.region_76 = 'None' OR s.region_76 is NULL OR s.region_76 = '')
	AND a.postal_code REGEXP '" . $prefixes . "'
	";

	if ($debug) echo "$query\n";
	$params = array( );
	$update =& CRM_Core_DAO::executeQuery( $query, $params );

	// find contributions without region within prefix
	if ($verbose) {
		$query  = "
		SELECT count(*) as total, sum(t.total_amount) as sum, s.chapter_77, s.region_76
		FROM civicrm_contribution AS t, " . $revenue_share_table . " AS s, civicrm_address AS a
		WHERE t.id = s.entity_id
		AND t.contact_id = a.contact_id
		AND a.is_primary = 1 
		AND t.contribution_type_id = 28
		AND t.contribution_status_id = 1
		AND (s.chapter_77 = 'Unassigned' OR s.chapter_77 = 'At Large')
		AND (s.region_76 = 'None' OR s.region_76 is NULL OR s.region_76 = '')
		AND a.postal_code REGEXP '" . $prefixes . "'
		GROUP BY s.chapter_77, s.region_76
		";
		if ($debug) echo "$query\n";
		$params = array( );
		$summary =& CRM_Core_DAO::executeQuery( $query, $params );
		echo  "Total\tSum\tChapter\tRegion\n" ;
		while ( $summary->fetch( ) ) {
			echo $results->display_name . "\t" . $summary->total . "\t" .$summary->sum. "\t" .$summary->chapter_77. "\t" .$summary->region_76. "\n" ;
		}
	}
}

?>