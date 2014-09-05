<?php

error_reporting(E_ERROR | E_WARNING);

// Initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton(); 
require_once "api/v2/Contact.php";
require_once "api/v2/Contribute.php";
require_once "api/v2/Membership.php";
require_once '/usr/local/bin/imba/includes/functions.inc.php';

// Variable Declarations
// 
// if no country (u, c, i) and segment (a, b, c, d)
// pair are specified do all segments
// ua ub uc ud ca cb cc cd ia ib ic id
//
if (count($argv) > 1) {
	$segments = $argv;
	array_shift($segments);
} else {
	$segments = array('ua', 'ub', 'uc', 'ud', 'ia', 'ib', 'ic', 'id');
}
$now = time();
$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));
$path = '/var/local/imba/itn/';
$filename = "itn_postal-" . $today . "-" . $segment . ".csv";
$header_row = NULL;
$mailto    = "evan.chute@imba.com,rod.judd@imba.com,membership@imba.com";
//$mailto    = "evan.chute@imba.com,sallyc@contrino.com";
$from_mail = "evan.chute@imba.com";
$from_name = "Evan Chute";
$message   = "Postal ITN file attached\n";
$replyto   = $from_mail;
$subject   = &$first_row;
$count = 0;

foreach ( $segments as $key => $segment ) {
	echo "Processing segment " . $segment;
	// Process arguement
	switch(substr($segment, -1, 1)) {
		case "a": //usa segment a current members
			$month_b ='0';
			$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) + $month_b, date('d',$now), date('Y',$now)));
			$end_expiration = 'ANY DATE';
			$additional_where_clause ="AND m.status_id NOT IN (5,6,7) AND owner_membership_id IS NULL AND m.end_date>= '" . $start_expiration . "'";
			$first_row = "ITN postal segment " . $segment . " with end_date from " . $start_expiration . " to " . $end_expiration;		
			break;
		case "b": //usa segment b comp
			$month_b ='0';
			$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) + $month_b, date('d',$now), date('Y',$now)));
			$end_expiration = 'ANY DATE';
			$additional_where_clause ="AND date(p.comp_itn_end_date_70)>='" . $start_expiration . "'";
			$first_row = "ITN postal segment " . $segment . " with comp_itn_end_date_70 from " . $start_expiration . " to " . $end_expiration;		
			break;
		case "c": //usa segment c 6 month expired members
			$month_a = '0';
			$month_b ='6';
			$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_b, date('d',$now), date('Y',$now)));
			$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_a, date('d',$now) - 1, date('Y',$now)));
			$additional_where_clause ="AND m.status_id NOT IN (5,6,7) AND owner_membership_id IS NULL AND m.end_date BETWEEN '" . $start_expiration . "' AND '" . $end_expiration . "'";
			$first_row = "ITN postal segment " . $segment . " with end_date from " . $start_expiration . " to " . $end_expiration;		
			break;
		case "d": //usa segment d 18 month non-member donors
			$month_b ='18';
			$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_b, date('d',$now), date('Y',$now)));
			$end_expiration = 'ANY DATE';
			$additional_where_clause ="AND t.receive_date >= '" . $start_expiration . "' AND m.id IS NULL";
			$first_row = "ITN postal segment " . $segment . " with receive_date from " . $start_expiration . " to " . $end_expiration;		
			break;
		default:
			echo "Please specify segment ua ub uc ud ca cb cc cd ia ib ic id.\n";
			exit;
		}
	
	switch(substr($segment, 0, 1)) {
		case "u": //usa
			$country_where_clause=' = 1228';
			break;
		case "c": //canada 
			$country_where_clause=' = 1039';
			break;
		case "i": //international
			$country_where_clause=' NOT IN (1228, 1039)';
			break;
		default:
			echo "Please specify segment 'ua', 'ub', 'uc', or 'ud'.\n";
			exit;
		}
	
	// Open the write file
	$fh = fopen($path . $filename, 'w') or die("can't open file");
	fwrite($fh, "\"" . $first_row . "\"\n");
	
	// select itn recipients
	$query  = "
	SELECT c.id as contact_id, m.id as membership_id, m.end_date
	FROM civicrm_contact c
	LEFT JOIN civicrm_address a ON c.id=a.contact_id AND a.is_primary=1
	LEFT JOIN civicrm_membership m ON c.id=m.contact_id 
	LEFT JOIN civicrm_contribution t ON c.id=t.contact_id 
	LEFT JOIN civicrm_value_campaign_preferences_10 p on c.id=p.entity_id
	WHERE a.country_id " . $country_where_clause . "
	AND (a.street_address IS NOT NULL AND a.street_address!='')
	AND c.do_not_mail = 0
	AND c.is_deleted = 0
	AND (p.itn_69 IS NULL OR p.itn_69='Always' OR p.itn_69='Automatic')
	" . $additional_where_clause . "
	GROUP BY c.id
	ORDER BY m.end_date,c.id
	";
	//echo $query;
        $message .= "\nQuery:\n" . $query;
	
	$params = array( );
	$memberships_to_renew =& CRM_Core_DAO::executeQuery( $query, $params );
	while ( $memberships_to_renew->fetch( ) ) {
		$fulfillment_details = array();
		$membership_chapters = array();
		$total_amount = NULL;
		$count++;
		
		// retrieve record
		$contact_id = $memberships_to_renew->contact_id; 
		//$contact_id = 218874;
		$contacts = array();
		$contact = array();
		$params = array(
				'contact_id' => $contact_id,
				'return.address_id' => 1,
				'return.display_name' => 1,
				'return.first_name' => 1,
				'return.last_name' => 1,
				'return.current_employer' => 1,
				'return.email' => 1,
				'return.street_address' => 1,
				'return.city' => 1,
				'return.state_province' => 1,
				'return.postal_code' => 1,
				'return.postal_code_suffix' => 1,
				'return.country' => 1,
				'return.contact_type' => 1,
				'return.custom_79' => 1,
				'return.custom_80' => 1,
				);
		$contacts = ContactGet( $params );
		$contact  = $contacts[$contact_id];
	
		// the fulfillment array
		$fulfillment_details = array(
			'email'					=> $contact['email'],
			'end_date'				=> $memberships_to_renew->end_date,
			'first_name'			=> $contact['first_name'],
			'last_name'				=> $contact['last_name'],
			'organization_name'		=> $contact['current_employer'],
			'street_address'		=> $contact['street_address'],
			'supplemental_address_1'=> $contact['supplemental_address_1'],
			'city'					=> $contact['city'],
			'state_province'		=> $contact['state_province'],
			'postal_code'			=> $contact['postal_code'],
			'postal_code_suffix'	=> $contact['postal_code_suffix'],
			'country'				=> $contact['country'],
			'contact_id'			=> $contact['contact_id'],
			'address_id'			=> $contact['address_id'],
			);
	
		// header row for csv
		if (is_null($header_row)) {
			foreach ( $fulfillment_details as $key => $value ) {
				$header_row .= "\"" . $key . "\",";
			}
			$header_row .= "\n";
			fwrite($fh, $header_row);
		}
	
		// organization logic
		if ($contact['contact_type'] == "Organization") {
			// organization name
			$fulfillment_details['organization_name'] = $contact['display_name'];
			
			// first and last name
			$query  = "SELECT first_name, last_name FROM civicrm_contact c
			LEFT JOIN civicrm_relationship r ON c.id=r.contact_id_a
			WHERE (r.end_date>='" . $today . "' or r.end_date IS NULL)
			AND r.is_active=1
			AND r.relationship_type_id=17
			AND r.contact_id_b=" . $contact_id . "
			GROUP BY contact_id_b";
			$params = array( );		
			$select_result =& CRM_Core_DAO::executeQuery( $query, $params );
			if ( $select_result->fetch( ) ) {
				$fulfillment_details['first_name'] = $select_result->first_name;
				$fulfillment_details['last_name'] = $select_result->last_name;
			} else {
				$query  = "SELECT first_name, last_name FROM civicrm_contact c
				LEFT JOIN civicrm_relationship r ON c.id=r.contact_id_a
				WHERE (r.end_date>='" . $today . "' or r.end_date IS NULL)
				AND r.is_active=1
				AND r.relationship_type_id=4
				AND r.contact_id_b=" . $contact_id . "
				GROUP BY contact_id_b";
				$params = array( );		
				$select_result =& CRM_Core_DAO::executeQuery( $query, $params );
				if ( $select_result->fetch( ) ) {
					$fulfillment_details['first_name'] = $select_result->first_name;
					$fulfillment_details['last_name'] = $select_result->last_name;
				} else {
					$fulfillment_details['first_name'] = "IMBA Supporter";
				}
			}
		}
		
		// build file
		$line = NULL;
		foreach ( $fulfillment_details as $key => $value ) {
			$line .= "\"" . $value . "\",";
		}
		$line .= "\n";
		fwrite($fh, $line);
		//print_r($fulfillment_details);
		//break;
	}
	fclose($fh);
	echo $first_row . " (" . $count . " records)\n";
	
	// Mail file
	mail_attachment($filename, $path, $mailto, $from_mail, $from_name, $replyto, $subject, $message);
	
	// basic debug
	/*		
	echo "<pre>";
	echo $query . "\n";
	echo "Contribution ";
	print_r($contribution);
	echo "Contact ";
	print_r($contact);
	echo "Contact chapters ";
	print_r($membership_chapters);
	echo "Membership ";
	print_r($membership);
	echo "fulfillment_details ";
	print_r($fulfillment_details);
	echo "#############\n";
	echo "</pre><p />\n\n";	
	*/
}
?>
