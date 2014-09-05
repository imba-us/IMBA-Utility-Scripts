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

// Variable declarations
$contribution_type_ids = "2, 28, 31, 32";
$contrino_product_ids = "0, 3, 14";
$now = time();
$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));
$path = '/var/local/imba/fulfillment/';
$bin_path = '/usr/local/bin/imba/';
$filename = "membership_fulfillment_contrino-" . $today . ".csv";
$first_row = "Membership fulfillment for Contrino processed on " . $today;
$header_row = NULL;
//"\"First Name\",\"Last Name\",\"Organization Name\",\"Street Address\",\"Supplemental Address 1\",\"City\",\"State\",\"Postal Code\",\"Postal Code Suffix\",\"Country\",\"Contributuion Type\",\"Total Amount\",\"End Date\",\"Product Name\",\"Product Option\",\"Region\",\"Chapter\",\"Internal Contact ID\"";
$mailto    = "evan.chute@imba.com,rod.judd@imba.com,membership@imba.com";
//$mailto    = "evan.chute@imba.com";
$from_mail = "evan.chute@imba.com";
$from_name = "Evan Chute";
$message   = $first_row;
$replyto   = $from_mail;
$subject   = $first_row;
$count = 0;

// Open write file
$fh = fopen($path . $filename, 'w') or die("can't open file");
fwrite($fh, "\"" . $first_row . "\"\n" . $header_row . "\n");

// Get memberships to fulfill
$query  = "SELECT c.id,c.contact_id,p.product_id
FROM civicrm_contribution AS c
LEFT OUTER JOIN civicrm_contribution_product AS p ON c.id=p.contribution_id
LEFT OUTER JOIN civicrm_address AS a ON c.contact_id=a.contact_id 
WHERE c.contribution_type_id IN (" . $contribution_type_ids . ") 
AND (p.product_id IN (" . $contrino_product_ids . ") OR p.product_id IS NULL)
AND (a.is_primary=1 OR a.is_primary IS NULL)
AND a.country_id=1228
AND c.contribution_status_id=1
AND c.total_amount>=0
AND thankyou_date IS NULL";
//AND (thankyou_date IS NULL OR date(thankyou_date)='20100311')";
//echo $query;
//exit;

$params = array( );
$contributions_to_fulfill =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $contributions_to_fulfill->fetch( ) ) {
	// Count records
	$count++;
	
	// Sent empty arrays
	$fulfillment_details = array();
	$contact = array();
	$contribution = array();
	$membership = array();

	// retrieve record
	$contact_id = $contributions_to_fulfill->contact_id; 
	$params = array(
			'contact_id' => $contact_id,
			'return.address_id' => 1,
			'return.contact_id' => 1,
			'return.contact_type' => 1,
			'return.display_name' => 1,
			'return.first_name' => 1,
			'return.last_name' => 1,
			'return.organization_name' => 1,
			'return.email' => 1,
			'return.custom_79' => 1,
			'return.custom_80' => 1,
			'return.street_address' => 1,
			'return.supplemental_address_1' => 1,
			'return.city' => 1,
			'return.state_province' => 1,
			'return.postal_code' => 1,
			'return.postal_code_suffix' => 1,
			'return.country' => 1,
			);
	$contacts = ContactGet( $params );
	$contact  = $contacts[$contact_id];

	// Get individual address for blank organization address
	if (!$contact['street_address']) {
		$query  = "SELECT street_address, supplemental_address_1, city, abbreviation, postal_code, postal_code_suffix, 'UNITED STATES' as country FROM civicrm_contact c, civicrm_address a, civicrm_state_province s 
WHERE c.id=a.contact_id 
AND a.state_province_id=s.id
AND a.is_primary=1
AND c.employer_id='". $contact_id . "'";

		$params = array( );		
		$address_select_result =& CRM_Core_DAO::executeQuery( $query, $params );
		while ( $address_select_result->fetch( ) ) {
			$contact['street_address'] = $address_select_result->street_address;
			$contact['supplemental_address_1'] = $address_select_result->supplemental_address_1;
			$contact['city'] = $address_select_result->city;
			$contact['state_province'] = $address_select_result->state_province;
			$contact['postal_code'] = $address_select_result->postal_code;
			$contact['postal_code_suffix'] = $address_select_result->postal_code_suffix;
			$contact['country'] = $address_select_result->country;
		}
	}
	
	// organization name and primary contact name
	if ($contact['contact_type'] == "Organization") {
		$name_label = "Organization";
		$organization_contact_names = organization_contact_name($contact_id);
		if ($organization_contact_names['email']) $contact['email'] = $organization_contact_names['email'];
		$contact['first_name'] = $organization_contact_names['first_name'];
		$contact['last_name'] = $organization_contact_names['last_name'];
	} 
	// retrieve record
	$contribution_id = $contributions_to_fulfill->id;
	$params = array(
			'contribution_id' => $contribution_id,
			);
	$contribution = ContributionGet( $params );

	// Standardize region and chapter
	$contribution['custom_76'] = strtoupper($contribution['custom_76']);
	switch($contribution['custom_76']) {
		case 'SORBA':
		case 'PACIFIC':
		case 'MIDWEST':
		case 'ROCKIES':
			break;
		case 'MID_ATLANTIC':
			$contribution['custom_76'] = "MID-ATLANTIC";
			break;
		default:
			$contribution['custom_76'] = "IMBA US";
			break;
	}

	if (!$contribution['custom_77']) {
		$contribution['custom_77'] = "Unassigned";
	}

	// retrieve record
	$query  = "SELECT membership_id FROM civicrm_membership_payment WHERE contribution_id=" . $contribution_id;
	$params = array( );		
	$membership_select_result =& CRM_Core_DAO::executeQuery( $query, $params );
	while ( $membership_select_result->fetch( ) ) {
		$membership_id = $membership_select_result->membership_id;
	}
	$params = array(
			'contact_id' => $contact_id,
			'membership_id' => $membership_id,
			);

	$memberships = MembershipsGet( $params );
	$membership  = $memberships[$contact_id][$membership_id];
	
	// Set details array
	$fulfillment_details = array(
		'first_name'			=> $contact['first_name'],
		'last_name'				=> $contact['last_name'],
		'organization_name'		=> $contact['organization_name'],
		'street_address'		=> $contact['street_address'],
		'supplemental_address_1'=> $contact['supplemental_address_1'],
		'city'					=> $contact['city'],
		'state_province'		=> $contact['state_province'],
		'postal_code'			=> $contact['postal_code'],
		'postal_code_suffix'	=> $contact['postal_code_suffix'],
		'country'				=> $contact['country'],
		'membership_name'		=> $membership['membership_name'],
		//'source'				=> $contribution['contribution_source'],
		'total_amount'			=> $contribution['total_amount'],
		//'status'				=> $contribution['contribution_status_id'],
		//'payment_instrument'	=> $contribution['payment_instrument'],
		'end_date'				=> print_date($membership['end_date']),
		'product_name'			=> $contribution['product_name'],
		'product_option'		=> $contribution['product_option'],
		//'thankyou_date'			=> print_date($contribution['thankyou_date']),
		//'batch_code'			=> $contribution['custom_81'],
		//'batch_date'			=> $contribution['custom_15'],
		'receive_date'			=> $contribution['receive_date'],
		'region'				=> $contribution['custom_76'],
		'chapter'				=> $contribution['custom_77'],
		'contact_id'			=> $contact_id,
		'address_id'			=> $contact['address_id'],
	);

	//print_r($fulfillment_details);
	//print_r($contact);
	//print_r($contribution);
	//print_r($membership);
	//exit;
	
	// header row for csv
	if (is_null($header_row)) {
		foreach ( $fulfillment_details as $key => $value ) {
			$header_row .= "\"" . $key . "\",";
		}
		$header_row .= "\n";
		fwrite($fh, $header_row);
	}
	
	// Write to file
	$last = count($fulfillment_details);
	$item = 0;
	$line = NULL;
	foreach ( $fulfillment_details as $key => $value ) {
		$item++;
		$line .= "\"" . $value . "\"";
		if ($item == $last) {
			$line .= "\n";
		} else {
			$line .= ",";
		}
	}
	// Write to file
	fwrite($fh, $line);
	
	// Build "IN" select 
	if ($count > 1) $fulfilled_ids .= ", ";
	$fulfilled_ids .= $contribution_id;
	//if ($count > 25) break;
}
// Close file
fclose($fh);

// Console output
echo $first_row . " (" . $count . " records)\n";

// Mail file
mail_attachment($filename, $path, $mailto, $from_mail, $from_name, $replyto, $subject, $message);

// Update thankyou_date
$query = "update civicrm_contribution set thankyou_date=now() where id in (" . $fulfilled_ids . ")";
$params = array( );		
if ($count > 0 ) $contribution_update_result =& CRM_Core_DAO::executeQuery( $query, $params );
echo $query;
?>