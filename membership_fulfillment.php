<?php
error_reporting(E_ERROR | E_WARNING);

// Process arguement
$segment = $argv[1];
switch($segment) {
	case "a": //fulfillment segment a -- everthing but patrol
		$contribution_type_ids = "28, 30, 31, 32";
		$corporate_contribution_type_id = "30";
		$contrino_product_ids = "0, 3, 14";
		$select_statement = "WHERE (a.country_id != 1039 
		AND ((c.contribution_type_id IN (" . $contribution_type_ids . ") AND (p.product_id NOT IN (" . $contrino_product_ids . ") AND p.product_id IS NOT NULL)) 
		OR c.contribution_type_id = " . $corporate_contribution_type_id ." 
		OR (c.contribution_type_id IN (" . $contribution_type_ids . ") AND a.country_id != 1228)))";
		$mailto = "evan.chute@imba.com,rod.judd@imba.com,membership@imba.com";
		//$mailto    = "evan.chute@imba.com";
		break;
	case "p": //fulfillment segment p -- patrol 
		$contribution_type_ids = "29";
		$contrino_product_ids = NULL;
		$select_statement = "WHERE c.contribution_type_id = " . $contribution_type_ids;
		$mailto = "evan.chute@imba.com,membership@imba.com,marty.caivano@imba.com";
		break;
	default:
		echo "Please specify segment 'a', or 'p'.\n";
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
$individual_letter = 'individual_letter.html';
$nmbp_group_letter = 'nmbp_group_letter.html';
$nmbp_patroller_letter = 'nmbp_patroller_letter.html';
$sorba_individual_letter = 'sorba_individual_letter.html';
$corporate_letter = 'corporate_letter.html';
$club_letter = 'club_letter.html';
*/
$now = time();
$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));
$path = '/var/local/imba/fulfillment/';
$bin_path = '/usr/local/bin/imba/';
$include_path = $bin_path . 'includes/';
$filename = "membership_fulfillment_in-house_segment-" . $today . "-" . $segment;
$first_row = "Membership fulfillment for in-house segment " . $segment . " processed on " . $today;
$from_mail = "evan.chute@imba.com";
$from_name = "Evan Chute";
$replyto   = $from_mail;
$subject   = $first_row;
$count = 0;
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

// Chapter logos
$chapter_logos = array(
	//'Colorado Mountain Bike Association (COMBA)' => 'Rocky Mountain',
	'Delaware Trail Spinners (DTS)' => 'dts.gif',
	'Greater Oakridge Area Trail Stewards (GOATS)' => 'goats.gif',
	'Minnesota Off-Road Cyclists (MORC)' => 'morc.gif',
	'Northwest Trail Alliance (NWTA)' => 'nwta.gif',
	'SORBA' => 'sorba.gif',
	'NMBP' => 'nmbp.gif',
);

// Get memberships to fulfill
$query  = "SELECT c.id,c.contact_id,p.product_id
FROM civicrm_contribution AS c
LEFT OUTER JOIN civicrm_contribution_product AS p ON c.id=p.contribution_id
LEFT OUTER JOIN civicrm_address AS a ON c.contact_id=a.contact_id 
" . $select_statement . "
AND (a.is_primary=1 OR a.is_primary IS NULL)
AND c.contribution_status_id=1
AND c.total_amount>=0
AND thankyou_date IS NULL 
ORDER BY c.receive_date
LIMIT 200";
//echo $query; exit;

$params = array( );
$contributions_to_fulfill =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $contributions_to_fulfill->fetch( ) ) {	
	// Sent empty arrays
	$fulfillment_details = array();
	$contact = array();
	$contribution = array();
	$membership = array();

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
	//print_r($contact); exit;

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
	//print_r($contribution); exit;

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
	
	/*
	echo "\n<pre>\n";
	print_r($fulfillment_details);
	print_r($contact);
	print_r($contribution);
	print_r($membership);
	echo "\n</pre>\n";
	exit;
	*/
	
	switch ($membership['membership_name']) {
		case 'Individual':
		case 'Family':
			if ($contribution['custom_76'] == 'SORBA') {
				$letter_html = $msg_templates['Fulfillment: SORBA Individual'];
				$logo = $chapter_logos['SORBA'];
			} else {
				$letter_html = $msg_templates['Fulfillment: Individual'];
				$logo = $chapter_logos[$contribution['custom_76']];
			}
			break;
		case 'NMBP Patroller':
		case 'NMBP Ambassador':
				$letter_html = $msg_templates['Fulfillment: NMBP Patroller'];
			if ($segment == 'a') {
				$logo = $chapter_logos['NMBP'];
			} else {
				$logo = NULL;
			}
			break;
		case 'NMBP Group':
			$letter_html = $msg_templates['Fulfillment: NMBP Patroller'];
			if ($segment == 'a') {
				$logo = $chapter_logos['NMBP'];
			} else {
				$logo = NULL;
			}
			break;
		case 'Corporate':
			$letter_html = $msg_templates['Fulfillment: Corporate'];
			$logo = NULL;
			break;
		case 'Club':
		case 'Retailer':
		case 'Promoter':
			$letter_html = $msg_templates['Fulfillment: Club'];
			$logo = NULL;
			break;
		default:
			$letter_body = NULL;
			$logo = NULL;
	}

	if ($logo) {
		$left_logo_html = '<img style="margin-top: 35px;" width=90 src="http://www.imba.com/sites/default/files/membership_images/membership_card_images/' . $logo .'">';
		$top_logo_html = "&nbsp;";
	} else {
		$left_logo_html = "&nbsp;";
		$top_logo_html = "&nbsp;";
	}
	
	if (!$contribution['custom_76']) $contribution['custom_76'] = '&nbsp;';
	//echo "\nDebug for " . $contact_id . ' ' . $membership['membership_name'] . ' ' . $contribution['custom_76'] . ' ' . $contribution['custom_77'] . ' ' . $read_letter . ' ' . $logo . "\n";
	echo ".";

	if (!$letter_html) {
		echo "\nNo letter for " . $contact_id . ' ' . $membership['membership_name'] . ' ' . $contribution['custom_76'] . "\n";
	} else {
		// Count records
		$count++;
		
		// Prepare letter
		$html .= '<!-- Begin -->
<div style="page-break-after: always;">
<table>
<tr>
<td style="width: 380px; height: 60px;">&nbsp;</td>
<td style="width:  90px;">&nbsp;</td>
<td style="width: 180px;">&nbsp;</td>
</tr>
<tr>
<td align="left" valign="top">
	<div><strong>Member ID # </strong>' . $contribution['contact_id'] . ' &nbsp;<strong>Expires </strong>' . print_date($membership['end_date']) . ' &nbsp;<strong>Amount $</strong>' . $contribution['total_amount'] . '</div>
	<div>' . (($contribution['product_name']) ? $contribution['product_name'] . ' ' . $contribution['product_option'] . ' ' . $contribution['sku'] . '' : '&nbsp;') . '</div>
	<div class="xsmall">IMBA is a 501(c)3 not-for profit corporation. Donations are generally tax-deductible</div>
	<div class="xsmall">less the value of premiums received. IMBA\'s federal tax ID# is 77-0204066.</div>
</td>
<td rowspan="3"  height="175" valign="top"><center>' . $left_logo_html . '</center></td>
<td rowspan="3">
	<table>
	<tr><td style="width:80px; padding-top:30px;" class="small" valign="top" align="right">&nbsp;</td><td style="width:100px;"class="small" align="left">' . $top_logo_html . '</td></tr>
	<tr><td class="small" valign="top" align="right">Name: </td><td class="small" align="left">' . $contact['display_name'] . '</td></tr>
	<tr><td class="small" valign="top" align="right">ID: </td><td class="small" align="left">' . $contribution['contact_id'] . '</td></tr>
	<tr><td class="small" valign="top" align="right">Expiration: </td><td class="small" align="left">' . print_date($membership['end_date']) . '</td></tr>
	<tr><td class="small" valign="top" align="right">Membership: </td><td class="small" align="left">' . $membership['membership_name'] . '</td></tr>
	<tr><td class="small" valign="top" align="right">Chapter: </td><td class="small" align="left">' . $contribution['custom_77'] . '</td></tr>
	</table>
</td>
</tr>
<tr>
<td valign="top">
	<div style="margin-left: 40px;"><strong>' . $contact['display_name'] . '<br>' . $contact['street_address'] . (($contact['supplemental_address_1']) ? ', ' . $contact['supplemental_address_1'] : '') . '<br>' .
$contact['city'] . ', ' . $contact['state_province'] . ' ' . $contact['postal_code'] . (($contact['postal_code_suffix']) ? '-' . $contact['postal_code_suffix'] : '') . '<br>' . $contact['country'] . '</strong></div></td>
</tr>
<tr>
<td colspan="3">
<div style="margin-top:10px;">Dear ' . $contact['greeting_name'] . ',</div>
<div>' . $letter_html . '</div></td>
</tr>
</table>
</div>
<!-- End -->
'; 

	// Build "IN" select 
	if ($count > 1) $fulfilled_ids .= ", ";
	$fulfilled_ids .= $contribution_id;
	//if ($count > 5) break;
	//break;
	}
}

$html .= '</body></html>';
//echo $html; exit;

if ($count) {
	// dompdf
	require_once '/var/www/imba/sites/all/plugins/dompdf/dompdf_config.inc.php';
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
