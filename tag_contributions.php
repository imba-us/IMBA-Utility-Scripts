<?php
// Output
$verbose	= 1;
$debug		= 0;
$email		= 1;

// Require chapter id file from command line argument
if (count($argv) == 2) {
	$contact_id = $argv[1];

	// Initialize CiviCRM
	require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
	require_once 'CRM/Core/Config.php'; 
	$config =& CRM_Core_Config::singleton();
	require_once "api/v2/Contribution.php";
	require_once "api/v2/Contact.php";
	require_once '/usr/local/bin/imba/includes/functions.inc.php';

	// Get chapter details
	$params = array(
			'contact_id' => $contact_id,
			'return.organization_name' => 1,
			'return.nick_name' => 1,
			'return.custom_79' => 1,
			'return.custom_80' => 1,
			);
	$contacts = ContactGet( $params );
	$contact  = $contacts[$contact_id];
	if ($debug) print_r($contact);
	$region = $contact['custom_79'];
	$chapter = $contact['organization_name'] . ($contact['nick_name'] ? " (" . $contact['nick_name'] . ")" : '');

	// Verify chapter
	$query  = "SELECT value FROM  civicrm_option_value WHERE  value='" . $chapter . "' AND option_group_id=45";
	$params = array( );
	$check_results =& CRM_Core_DAO::executeQuery( $query, $params );
	if ( ! $check_results->fetch( ) ) {
		echo "Chapter not found in region/chapter options: " . $chapter . "\n";
		exit;
	} 
} else {
	echo "usage: $argv[0] chapter_id\n";
	exit;
}

// Variables
$note		= "Original contribution refunded " . date('Y-m-d h:m:s') . " to reassign to " . $chapter . ". ";
$batch_code = 'JASONB';
$recipient	= 'evan.chute@imba.com';
$message	= "Tagging stats for $chapter\n";
$subject	= "$chapter tagging stats";
$sql_append	= "
FROM civicrm_contribution t
LEFT JOIN civicrm_contact c on t.contact_id=c.id
LEFT JOIN civicrm_value_revenue_sharing_11 r on t.id=r.entity_id
WHERE t.contribution_type_id=28
AND t.total_amount!=0
AND t.receive_date>='2011-04-01'
AND t.contribution_status_id=1
AND t.contribution_page_id IS NULL
AND (
	r.chapter_77 IN ('At Large','Unassigned') 
	OR r.chapter_77 IS NULL
	)
AND c.id IN (
	SELECT DISTINCT c2.id FROM civicrm_contact c2
	LEFT JOIN civicrm_value_region_and_chapter_12 rc on c2.id=rc.entity_id
	LEFT JOIN civicrm_value_revenue_sharing_11 r2 on c2.id=r2.entity_id
	WHERE (
		rc.chapter_80 LIKE '%" . $chapter . "%'
		OR r2.chapter_77='" . $chapter . "'
	)
	AND is_deleted=0
)
ORDER BY t.id
";

// Stats
$outfile = getcwd() . "/" . $chapter . "-contribution_reference-" .date('Ymd-U') . ".txt";
$query  = "
SELECT count(*) as total_contributions, sum(t.total_amount) as total_amount, (sum(t.total_amount) * .6)  as imba_amount, (sum(t.total_amount) * .4) as chapter_amount
" . $sql_append ."
GROUP BY t.contribution_page_id";

if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $results->fetch( ) ) {
	$message .=  "
	Total Contributions: " . $results->total_contributions . "
	Total Amount: " . $results->total_amount . "
	IMBA Amount: " . $results->imba_amount . "
	Chapter Amount: " . $results->chapter_amount ."
	";
}

if ($verbose) {
	echo "\n$message";
	echo "\nContinue (y/n)? ";
	if (trim(fgets(STDIN)) != 'y') exit;
}

// Find current contributions for reference
$outfile = getcwd() . "/" . $chapter . "-contribution_reference-" .date('Ymd-U') . ".txt";
$query  = "
SELECT t.*,r.chapter_77
INTO OUTFILE '" . $outfile . "'
" . $sql_append;

if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
echo "\nContributions writen to: $outfile";

// Backup table
$table_name = "civicrm_contribution";
$backup_file = getcwd() . "/" . $chapter . "-" . $table_name . "-" .date('Ymd-U') . ".sql";
$query  = "
SELECT * INTO OUTFILE '" . $backup_file . "' FROM " . $table_name ."
";
if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
if (!is_file($backup_file)) {
	echo "\nError reading $backup_file";
	exit;
} 
echo "\nBackup writen to: $backup_file";

// Loop through contributions
$outfile = getcwd() . "/" . $chapter . "-contribution_reference-" .date('Ymd-U') . ".txt";
$query  = "
SELECT t.id as contribution_id
" . $sql_append;

if ($debug) echo "$query\n";
$params = array( );
$results =& CRM_Core_DAO::executeQuery( $query, $params );
$count = 0;
while ( $results->fetch( ) ) {
	$contribution_id = $results->contribution_id;

	// Get original
	$params = array(
		'contribution_id' => $contribution_id
	);
	$original_contribution =& civicrm_contribution_get($params);
	if ($debug) print_r($original_contribution);
	
	// Create negative (cancelled) contribution 
	$params = $original_contribution;
	$params['total_amount'] = -$original_contribution['total_amount'];
	$params['note'] = $note;
	$params['source'] = "Refunded: " . $contribution_id;
	$params['custom_13'] = NULL;
	$params['custom_15'] = '2011-12-30 00:00:00';
	$params['custom_81'] = $batch_code;
	$params['payment_instrument'] = 'Cash';
	$params['trxn_id'] = NULL;
	$params['invoice_id'] = NULL;
	$params['custom_19'] = '';
	$params['custom_20'] = '';
	$params['custom_21'] = '';
	$params['receive_date'] = date('Y-m-d 00:00:00');
	if ($debug) print_r($params);
	$negative_contribution =& civicrm_contribution_add($params);
	if ($debug) print_r($negative_contribution);
	
	// Create reassigned contribution
	$params['total_amount'] = $original_contribution['total_amount'];
	$params['source'] = "Reassigned: " . $contribution_id;
	$params['custom_76'] = $region;
	$params['custom_77'] = $chapter;
	if ($debug) print_r($params);
	$new_contribution =& civicrm_contribution_add($params);
	if ($debug) print_r($new_contribution);
	
	// Output
	$count++;
	$message_append = "\nProcessed: " . $contribution_id . ", Refund: " . $negative_contribution['id'] . ", Reassigned: " . $new_contribution['id'];
	echo $message_append;
	$message .= $message_append;
}

// Output
$message_append = "\nProcessed $count original contributions";
echo $message_append;
$message .= $message_append;

// Email results
if ($email) mail($recipient, $subject, $message);

// Write stats to file
$outfile = getcwd() . "/" . $chapter . "-tag_contributions-stats-" . date('Ymd-U') . ".txt";
$fh = fopen($outfile, 'w') or die("\nCan't open file $outfile");
fwrite($fh, $message);
fclose($fh);

echo "\nArchiving SQL files\n";
system('/bin/gzip "' . $backup_file . '"');

?>