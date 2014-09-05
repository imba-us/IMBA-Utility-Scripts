<?php
error_reporting(E_ERROR | E_WARNING);

// Process arguement
$segment = $argv[1];
switch($segment) {
	case 'c': //fulfillment segment c -- corporate
		$contribution_type_ids = "23, 24, 43";
		//$contribution_type_ids = "3, 5, 6, 8, 11, 18, 19, 25, 27, 43, 44";
		//$contribution_type_ids = "1, 3, 5, 6, 8, 11, 14, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 43, 44";
		/*
		"1";"Shared Revenue"
		"2";"Membership"
		"3";"Campaign Donation: Annual"
		"4";"Event Registration"
		"5";"Campaign Donation: Legal Advocacy"
		"6";"Campaign Donation: Trailbuilding"
		"8";"Campaign Donation: California"
		"11";"Campaign Donation: SORBA Annual"
		"14";"General Donation: 3rd Party Fundraiser"
		"16";"General Donation: Employer Matching"
		"17";"In-kind"
		"18";"General Donation: Team IMBA"
		"19";"General Donation"
		"20";"Foundation Grant"
		"21";"Foundation Grant: Annual"
		"22";"Planned"
		"23";"Corporate Sponsorship"
		"24";"Corporate Sponsorship: California"
		"25";"General Donation: Tribute"
		"26";"Government Grant"
		"27";"General Donation: National Bike Summit"
		"28";"Membership: Individual"
		"29";"Membership: NMBP"
		"30";"Membership: Corporate"
		"31";"Membership: Retailer"
		"32";"Membership: Club"
		"37";"Campaign Donation: Ontario Mountain Bike Leadership"
		"42";"Product Sale"
		"43";"Campaign Donation: Public Land Initiative"
		"44";"General Donation: Club Related"
		*/
		$mailto = "evan.chute@imba.com, wendy.kerr@imba.com, membership@imba.com";
		//$mailto = "evan.chute@imba.com";
		break;
	case 'a':
		$contribution_type_ids = "3, 5, 6, 8, 11, 18, 19, 25, 27, 44";
		//$mailto = "evan.chute@imba.com";
		$mailto = "evan.chute@imba.com, rod.judd@imba.com, membership@imba.com";
		break;
	default:
		echo "Please specify segment 'a' or 'c'.\n";
		exit;
	}

// Initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton(); 
require_once "api/v2/Contact.php";
require_once "api/v2/Contribute.php";
require_once "api/v2/Membership.php";
require_once '/usr/local/bin/imba/includes/functions.inc.php';

// Testing
$testing = 0;
if ($testing) {
	$mailto = "evan.chute@imba.com";
	$set_thankyou_date = 0;
	// Anything special for SQL?
	//$select_statement .= " AND c.contact_id=43465 ";
} else {
	$set_thankyou_date = 1;
}

// Variable declarations
/*
$corporate_letter = 'corporate_letter.html';
$pli_letter = 'pli_letter.html';
$fund_letter = 'fund_letter.html';
$fund_md_letter = 'fund_md_letter.html';
*/
$now = time();
$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));
$path = '/var/local/imba/fulfillment/';
$bin_path = '/usr/local/bin/imba/';
$include_path = $bin_path . 'includes/';
$filename = "contribution_fulfillment_in-house_-" . $today . "-" . $segment;
$first_row = "Contribution fulfillment for in-house segment " . $segment . " processed on " . $today;
$from_mail = "evan.chute@imba.com";
$from_name = "Evan Chute";
$replyto   = $from_mail;
$subject   = $first_row;
$count = 0;
$total_limit = 300;
$fulfilled_contributions = NULL;
$html = '<html>
  <head>
	<style type="text/css">		
		body {
			padding: 0px;
			font-family: Helvetica, sans-serif;
			font-size: 10pt
		}
		
		table {
			border-spacing: 0px
		}
		
		td {
			font-family: Helvetica, sans-serif;
			font-size: 10pt
		}
		
		th {
			font-family: Helvetica, sans-serif;
			font-size: 10pt
		}
		
		.xsmall {
			font-family: Helvetica, sans-serif;
			font-size: 7pt
		}
		
		.small {
			font-family: Helvetica, sans-serif;
			font-size: 8pt
		}
	</style>
  </head>
<body>
';

// Get templates
$query  = "SELECT *
FROM civicrm_msg_template
WHERE msg_title LIKE 'Fulfillment: %'";
//echo $query; exit;

$params = array( );
$msg_templates = array( );
$msg_templates_results =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $msg_templates_results->fetch( ) ) {
	$msg_templates[$msg_templates_results->msg_title] = $msg_templates_results->msg_html;
}
//print_r($msg_templates); exit;

// Get contributions to fulfill
$query  = "SELECT c.id, c.contact_id, c.total_amount, c.contribution_page_id, c.contribution_type_id, p.product_id
FROM civicrm_contribution AS c
LEFT OUTER JOIN civicrm_contribution_product AS p ON c.id=p.contribution_id
LEFT OUTER JOIN civicrm_address AS a ON c.contact_id=a.contact_id AND a.is_primary=1
WHERE c.contribution_type_id IN (" . $contribution_type_ids . ")
AND a.country_id!=1039
AND c.contribution_status_id=1
AND c.thankyou_date IS NULL
AND c.total_amount>0
AND date(c.receive_date)>='2010-01-01'
ORDER BY p.product_id, c.total_amount";
//LIMIT 0,100";
//echo $query; exit;

$params = array( );
$contributions_to_fulfill =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $contributions_to_fulfill->fetch( ) ) {
	// Unify product to fulfill or none
	if ($contributions_to_fulfill->product_id == 0 || $contributions_to_fulfill->product_id == 27 || is_null($contributions_to_fulfill->product_id)) $contributions_to_fulfill->product_id = 0;
	// skip fulfillment for certian conditions
	if ($segment == 'a' && $contributions_to_fulfill->total_amount < 250) {
		// skip fulfillment for non-MD online contributions with no gift and...
		if ($contributions_to_fulfill->contribution_page_id > 0 && $contributions_to_fulfill->product_id == 0) {
			// ...online and no gift
			echo "\n- skipping (1) $contributions_to_fulfill->id for $contributions_to_fulfill->contact_id with premium $contributions_to_fulfill->product_id amount $contributions_to_fulfill->total_amount from page $contributions_to_fulfill->contribution_page_id of type $contributions_to_fulfill->contribution_type_id\n";
			continue;
		} 
		/*
		// this needs work for AF -- add certian premium?
		else if ($contributions_to_fulfill->product_id != 0 && is_null($contributions_to_fulfill->contribution_page_id) && $contributions_to_fulfill->contribution_id == 3) {
			// ...offline annual fund donations with gift 
			echo "\n- skipping (2) $contributions_to_fulfill->id for $contributions_to_fulfill->contact_id with premium $contributions_to_fulfill->product_id amount $contributions_to_fulfill->total_amount from page $contributions_to_fulfill->contribution_page_id of type $contributions_to_fulfill->contribution_type_id\n";
			continue;
		}
		*/
	}
	echo "\n+ fulfilling (99) $contributions_to_fulfill->id for $contributions_to_fulfill->contact_id with $contributions_to_fulfill->product_id amount $contributions_to_fulfill->total_amount from page $contributions_to_fulfill->contribution_page_id of type $contributions_to_fulfill->contribution_type_id\n";
	//echo ".";
	
	// Sent empty arrays
	$fulfillment_details = array();
	$contact = array();
	$contribution = array();
	$organization_contact_names = array();
	$read_letter = NULL;
	$name_label = "Name";

	// retrieve record
	$contact_id = $contributions_to_fulfill->contact_id; 
	$params = array(
			'contact_id' => $contact_id,
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
		$query  = "SELECT street_address, supplemental_address_1, city, abbreviation, postal_code, postal_code_suffix, 'UNITED STATES' as country
FROM civicrm_contact c, civicrm_address a, civicrm_state_province s 
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
		$contact['greeting_name'] = $organization_contact_names['first_name'];
	} else {
		$contact['greeting_name'] = $contact['first_name'];
	}

	// retrieve record
	$contribution_id = $contributions_to_fulfill->id;
	$params = array(
			'contribution_id' => $contribution_id,
			);
	$contribution = ContributionGet( $params );

	$contribution_type_parts = explode(':',$contribution['contribution_type']);
	if (count($contribution_type_parts) > 1) {
		$contribution['contribution_type'] = trim($contribution_type_parts[1]);
	}
	/*
	
	echo "\n<pre>\n";
	print_r($contribution);
	echo "\n</pre>\n";
	print_r($fulfillment_details);
	print_r($contact);
	print_r($membership);
	exit;
	*/
	
	switch ($contribution['contribution_type']) {
		case 'Corporate Sponsorship':
		case 'Corporate Sponsorship: California':
		case 'California':
			$letter_html = $msg_templates['Fulfillment: Corporate'];
			break;
		case 'Campaign Donation: Public Lands Initiative':
		case 'Public Lands Initiative':
			$letter_html = $msg_templates['Fulfillment: PLI'];
			break;
		default:
			if ($contribution['total_amount'] >= 250) {
				$letter_html = $msg_templates['Fulfillment: Fund Major Donor'];
			} else {
				$letter_html = $msg_templates['Fulfillment: Fund'];
			}
			break;
	}


	if (!$letter_html) {
		echo "\nNo letter for " . $contact_id . ' ' . $contribution['contribution_type'] . "\n";
	} else {
		// Count records
		$count++;

		// Prepare letter
		$html .= '<!-- Begin -->
<div style="page-break-after: always;">
<table>
  <tr>
    <td style="width:310px; height: 50px">&nbsp;</td>
    <td style="width:5px">&nbsp;</td>
    <td style="width:270px">&nbsp;</td>
  </tr>
  <tr>
    <td align="left" valign="top">&nbsp;</td>
    <td rowspan="3"  style="height:175px" align="right" valign="top">&nbsp;</td>
    <td align="left" valign="top">&nbsp;</td>
  </tr>
  <tr>
    <td valign="top">
    	<div style="margin-left: 40px;"><strong>' . $contact['display_name'] . '<br>' . $contact['street_address'] . (($contact['supplemental_address_1']) ? ', ' . $contact['supplemental_address_1'] : '') . '<br>' .
$contact['city'] . ', ' . $contact['state_province'] . ' ' . $contact['postal_code'] . (($contact['postal_code_suffix']) ? '-' . $contact['postal_code_suffix'] : '') . '<br>' . $contact['country'] . '</strong></div></td>
     <td rowspan="2"><table style="width:165px; border: 1px black solid; padding: 10px;">
		<tr><td class="small" valign="top" align="right" style="width:75px;">' . $name_label . ': </td><td class="small" align="left" width="150">' . $contact['display_name'] . '</td></tr>
		' . (($organization_contact_names['first_name'] || $organization_contact_names['last_name']) ? '<tr><td class="small" valign="top" align="right">Primary Contact: </td><td class="small" align="left">' . $organization_contact_names['first_name'] . ' ' . $organization_contact_names['last_name'] . '</td></tr>' : '') . '
		<tr><td class="small" valign="top" align="right">Contact Email: </td><td class="small" align="left">' . $contact['email'] . '</td></tr>
		<tr><td class="small" valign="top" align="right">ID: </td><td class="small" align="left">' . $contribution['contact_id'] . '</td></tr>
		<tr><td class="small" valign="top" align="right">Contribution: </td><td class="small" align="left">' . $contribution['contribution_type'] . '</td></tr>
		<tr><td class="small" valign="top" align="right">Amount: </td><td class="small" align="left">$' . $contribution['total_amount'] . '</td></tr>
		<tr><td class="small" valign="top" align="right">Date: </td><td class="small" align="left">' . print_date($contribution['receive_date']) . '</td></tr>
		' . (($contribution['product_name']) ? '<tr><td class="small" valign="top" align="right">Gift: </td><td class="small" align="left">' . $contribution['product_name'] . ' ' . $contribution['product_option'] . ' (' . $contribution['sku'] . ')</td></tr>' : '') . '		
		<tr><td class="xsmall" valign="bottom" align="center" colspan="2" height="20">IMBA is a 501(c)(3) non-profit organization. Tax ID Number: 77-0204066</td></tr>
		</table>
	</td>
 </tr>
  <tr>
    <td valign="top">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="3"><div style="margin-left: 20px;">Dear ' . $contact['greeting_name'] . ',</div>
    <div style="margin-left: 20px; width: 550px;">' . $letter_html . '</div></td>
  </tr>
</table>
</div>
<!-- End -->
'; 

	// Build "IN" select 
	if ($count > 1) $fulfilled_ids .= ", ";
	$fulfilled_ids .= $contribution_id;
	if ($count >= $total_limit) break;
	//break;
	}
}
$html .= '</body></html>';
//echo $html;

if ($count) {
	// dompdf
	require_once 'packages/dompdf/dompdf_config.inc.php';
	spl_autoload_register('DOMPDF_autoload');
	$dompdf = new DOMPDF( );
	$dompdf->load_html( $html );
	//$dompdf->set_paper ('letter', 'portrait');
	$dompdf->render( );
	//$dompdf->stream('membership_letters.pdf');
	$pdf = $dompdf->output();
	//echo $pdf; exit;
	
	// Write files
	//file_put_contents($path . $filename  . ".html", $html);
	file_put_contents($path . $filename  . ".pdf", $pdf);
	
	// Message
	$message = "\n". $first_row . " (" . $count . " records)\n";
	echo $message;
	
	// Mail file
	mail_attachment($filename . ".pdf", $path, $mailto, $from_mail, $from_name, $replyto, $subject, $message);
	
	// Update thankyou_date
	$query = "update civicrm_contribution set thankyou_date=now() where id in (" . $fulfilled_ids . ") and thankyou_date IS NULL";
	$params = array( );		
	if ($set_thankyou_date) {
		$contribution_update_result =& CRM_Core_DAO::executeQuery( $query, $params );
		echo $query . "\n";
	}
} else {
	echo "\nNothing to fulfill\n";
}
?>
