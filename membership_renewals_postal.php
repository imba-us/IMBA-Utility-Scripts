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
/*
Individual	1
NMBP Patroller	2
NMBP Ambassador	3
Retailer	4
Family	5
Lifetime	6
Club	7
Corporate	8
NMBP Group	9
Event Promoter	10
Chapter	11
*/
$membership_type_ids = "1, 2, 3, 4, 5, 7, 8, 9, 10, 30";
$segment = $argv[1];
$now = time();
$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));
$path = '/var/local/imba/renewals/';
$filename = "postal_renewals-" . $today . "-" . $segment . ".csv";
$header_row = NULL;
$mailto    = "evan.chute@imba.com,rod.judd@imba.com,membership@imba.com";
//$mailto    = "evan.chute@imba.com";
$from_mail = "evan.chute@imba.com";
$from_name = "Evan Chute";
$message   = "Postal renewal file attached\n";
$replyto   = $from_mail;
$subject   = &$first_row;
$count = 0;

// Process arguement
switch($segment) {
	case "a": //renewal segment a
		$month_a = '2';
		$month_b ='1';
		$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) + $month_b, date('d',$now), date('Y',$now)));
		$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) + $month_a, date('d',$now) - 1, date('Y',$now)));
		break;
	case "b": //renewal segment b
		$month_a = '1';
		$month_b ='0';
		$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) + $month_b, date('d',$now), date('Y',$now)));
		$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) + $month_a, date('d',$now) - 1, date('Y',$now)));
		break;
	case "c": //renewal segment c
		$month_a = '0';
		$month_b ='2';
		$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_b, date('d',$now), date('Y',$now)));
		$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_a, date('d',$now) - 1, date('Y',$now)));
		break;
	case "d": //renewal segment d
		$month_a = '2';
		$month_b ='6';
		$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_b, date('d',$now), date('Y',$now)));
		$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_a, date('d',$now) - 1, date('Y',$now)));
		break;
	case "s": // short lapsed
		$month_a = '6';
		$month_b ='12';
		$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_b, date('d',$now), date('Y',$now)));
		$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_a, date('d',$now) - 1, date('Y',$now)));
		break;
	case "l": // long lapsed
		$month_a = '12';
		$month_b ='18';
		$start_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_b, date('d',$now), date('Y',$now)));
		$end_expiration = date('Y-m-d',  mktime(0, 0, 0, date('m',$now) - $month_a, date('d',$now) - 1, date('Y',$now)));
		break;
	default:
		echo "Please specify segment 'a', 'b', 'c', 'd', 's', or 'l'.\n";
		exit;
	}

// Chapters
$chapters = get_chapters();
if (!$chapters) exit;

/*
$chapters = array(
	'Albemarle/Troy-NC (Uwharrie)' => 'SORBA',
	'Athens-GA' => 'SORBA',
	'Atlanta-GA' => 'SORBA',
	'Augusta-GA and Aiken-SC (CSRA)' => 'SORBA',
	'CHARLOTTE-NC (PASA)' => 'SORBA',
	'Chattanooga-TN (CASA)' => 'SORBA',
	'Colorado Mountain Bike Association (COMBA)' => 'Rocky Mountain',
	'Delaware Trail Spinners (DTS)' => 'Mid-Atlantic',
	'Ellijay-GA (EMBA)' => 'SORBA',
	'Gainesville-GA' => 'SORBA',
	'Greater Oakridge Area Trail Stewards (GOATS)' => 'Pacific',
	'Greenville-SC (Upstate)' => 'SORBA',
	'Gwinnett-GA (GATR)' => 'SORBA',
	'Habersham-GA (UC3)' => 'SORBA',
	'Huntsville-AL' => 'SORBA',
	'Knoxville-TN (AMBC)' => 'SORBA',
	'LaGrange/Columbus-GA and Opelika-AL (CVA)' => 'SORBA',
	'Middle Georgia (OMBA)' => 'SORBA',
	'Middle Tennessee' => 'SORBA',
	'Minnesota Off-Road Cyclists (MORC)' => 'Midwest',
	'Northwest Georgia' => 'SORBA',
	'Northwest Trail Alliance (NWTA)' => 'Pacific',
	'Paulding-GA' => 'SORBA',
	'Pisgah Area/Western NC (PAS)' => 'SORBA',
	'Raleigh/Durham/Chapel Hill-NC (TORC)' => 'SORBA',
	'Roswell/Alpharetta-GA (RAMBO)' => 'SORBA',
	'Tallahassee-FL' => 'SORBA',
	'Tuscaloosa-AL (WAMBA)' => 'SORBA',
	'Wilmington-NC' => 'SORBA',
	'Woodstock-GA' => 'SORBA',
	//'At Large' => 'None',
	//'Unassigned' => 'None',
);
*/

// Open the write file
$first_row = "Membership renewals postal segment " . $segment . " with end_date from " . $start_expiration . " to " . $end_expiration . " of type ". $membership_type_ids;
$fh = fopen($path . $filename, 'w') or die("can't open file");
fwrite($fh, "\"" . $first_row . "\"\n");

// Patrol groups
$patrol_groups = array(
	'ALAFIA RIVER BIKE PATROL' => '',
	'ALLEGHENY RANGE RIDERS MTB PATROL' => '',
	'BACKCOUNTRY BICYCLE TRAILS CLUB PATROL' => '',
	'BACKCOUNTRY PATROL- LAKE TAHOE NV STATE PARK' => '',
	'BACKCOUNTRY RESCUE EMERGENCY MEDICAL SUPPORT' => '',
	'BACKCOUNTRY TRAIL PATROL MN' => '',
	'BARMY DOGS PATROL' => '',
	'BHMBA PATROL' => '',
	'BMA PATROL' => '',
	'BMBA EL PASO' => '',
	'BOYETTE BIKE PATROL' => '',
	'BRIDGERLAND NMBP' => '',
	'BROWARD COUNTY PARKS MOUNTAIN BIKE PATROL' => '',
	'BTCEB PATROL' => '',
	'CAMBA PATROL' => '',
	'CENTRAL ARIZONA MOUNTAIN BIKE PATROL' => '',
	'CENTRAL IOWA TRAIL PATROL' => '',
	'CENTRAL OHIO MOUNTAIN BIKE PATROL' => '',
	'CLIMB TRAIL PATROL' => '',
	'COLOR COUNTRY MBP' => '',
	'CORA NMBP' => '',
	'COULEE REGION PATROL' => '',
	'CSRA SORBA' => '',
	'DEER VALLEY MTN BIKE PATROL' => '',
	'DELAWARE TRAIL SPINNERS MOUNTAIN BIKE PATROL' => '',
	'DIAMOND PEAKS MOUNTAIN BIKE PATROL' => '',
	'DURANGO - SAN JUAN MOUNTAIN BIKE PATROL' => '',
	'FRIENDS OF HARBISON ST FOREST TRAIL PATROL' => '',
	'FRONT RANGE MOUNTAIN BIKE PATROL' => '',
	'GAINESVILLE-SORBA MOUNTAIN BIKE PATROL' => '',
	'GORBA PATROL' => '',
	'GORC PATROL' => '',
	'GRAND VALLEY MTN BIKE PATROL' => '',
	'GREATER BOSTON MTB PATROL' => '',
	'GREATER CHATTANOOGA MT BIKE PATROL' => '',
	'GREEN MOUNTAIN BIKE PATROL' => '',
	'GROC' => '',
	'GUMBA PATROL' => '',
	'HARDWOOD HILLS MTB PATROL' => '',
	'HUB ATLANTIC' => '',
	'INDIANA MOUNTAIN BIKE PATROL' => '',
	'IRON VICTIM' => '',
	'IRVINE RANCH LAND RESERVE TRUST PATROL' => '',
	'JAPAN MOUNTAIN BIKE ASSOCIATION' => '',
	'JORBA-ALLAMUCHY MTB PATROL' => '',
	'JORBA TRAIL AMBASSADORS' => '',
	'KINGDOM TRAILS BIKE PATROL' => '',
	'LAFIA RIVER BIKE PATROL' => '',
	'LAWRENCE MOUNTAIN BIKE PATROL' => '',
	'LEE COUNTY BIKE PATROL' => '',
	'MAGIC MTN SKI/BIKE PATROL' => '',
	'MARIN MOUNTAIN BIKE PATROL' => '',
	'MARTIN BIKE PATROL' => '',
	'MEDICINE BOW MOUNTAIN BIKE PATROL' => '',
	'Medicine Wheel Trail Advocates' => '',
	'MERCER COUNTY BICYCLE PATROL' => '',
	'MERE MORTALS MOUNTAIN BIKE PATROL' => '',
	'MIAMI VALLEY MOUNTAIN BIKE ASSN PATROL' => '',
	'MICHIGAN MOUNTAIN BIKE PATROL' => '',
	'MID SOUTH TRAILS ASSOCIATION' => '',
	'MIDWEST MOUNTAIN BIKE PATROL' => '',
	'MORC MOUNTAIN BIKE PATROL' => '',
	'NEW ENGLAND MTN BIKE PATROL' => '',
	'NEW JERSEY REGION BIKE PATROL' => '',
	'NEW YORK BIKE PATROL' => '',
	'NOCELA SAR PATROL' => '',
	'NORTH HILLS MOUNTAINEERS' => '',
	'NORTH TEXAS MOUNTAIN BIKE PATROL' => '',
	'OMBA BIKE PATROL' => '',
	'PAJARITO MTN BIKE PATROL' => '',
	'PEORIA AREA MOUNTAIN BIKE PATROL' => '',
	'Pisgah Area SORBA Mountain Bike Patrol' => '',
	'QCFORC MTB PATROL' => '',
	'RAMAPO TRAIL RIDERS' => '',
	'ROANOKE VALLEY MOUNTAIN BIKE PATROL' => '',
	'SDMBA/NMBP ' => '',
	'SORBA - MIDDLE TN PATROL' => '',
	'SORBA RAMBO MOUNTAIN BIKE PATROL' => '',
	'SORBA WOODSTOCK MOUNTAIN BIKE PATROL' => '',
	'TAHOE RIM TRAIL AMBASSADORS' => '',
	'TARHEEL TRAILBLAZERS BIKE PATROL' => '',
	'TORC MOUNTAIN BIKE PATROL' => '',
	'WEB MOUNTAINBIKE PATROL' => '',
	'WESTERN NEW YORK NATIONAL MOUNTAIN BIKE PATROL' => '',
	'WILDERNESS TRAILS BIKE PATROL' => '',
	'WISCONSIN MTB ASSN NMBP' => '',
	'WORBA PATROL' => '',
);

// Find memberships needing to renew
$query  = "SELECT distinct m.id, m.contact_id, m.id as membership_id, m.membership_type_id, m.end_date
FROM civicrm_membership m
LEFT JOIN civicrm_address a ON m.contact_id=a.contact_id AND a.is_primary=1
LEFT JOIN civicrm_contact c ON m.contact_id=c.id 
LEFT OUTER JOIN civicrm_value_campaign_preferences_10 p on m.contact_id=p.entity_id
WHERE m.end_date BETWEEN '" . $start_expiration . "' AND '" . $end_expiration . "'
AND m.status_id NOT IN (5,6,7)
AND m.membership_type_id IN (" . $membership_type_ids . ")
AND a.country_id = 1228
AND (a.street_address IS NOT NULL AND a.street_address!='')
AND c.do_not_mail = 0
AND c.is_deleted != 1
AND (p.send_renewals_74 IS NULL OR p.send_renewals_74=1)
AND owner_membership_id IS NULL
ORDER BY m.end_date,m.contact_id";

$params = array( );
$memberships_to_renew =& CRM_Core_DAO::executeQuery( $query, $params );
while ( $memberships_to_renew->fetch( ) ) {
	if (primary_membership($memberships_to_renew->contact_id, $memberships_to_renew->membership_id, $memberships_to_renew->end_date, $memberships_to_renew->membership_type_id)) {
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
	
		// retrieve membership
		$membership_id = $memberships_to_renew->id;
		$memberships = array();
		$membership = array();
		$params = array(
				'contact_id' => $contact_id,
				'membership_id' => $membership_id,
				);
		$memberships = MembershipsGet( $params );
		$membership  = $memberships[$contact_id][$membership_id];
	
		// retrieve contribution
		$query  = "SELECT MAX(contribution_id) contribution_id FROM civicrm_membership_payment WHERE membership_id=" . $membership_id;
		$contribution_id = NULL;
		$contribution = array();
		$params = array( );		
		$membership_select_result =& CRM_Core_DAO::executeQuery( $query, $params );
		while ( $membership_select_result->fetch( ) ) {
			$contribution_id = $membership_select_result->contribution_id;
		}
		if ($contribution_id) {
			$params = array(
					'contribution_id' => $contribution_id,
					//'return.custom_76' => 1,
					//'return.custom_77' => 1,
					);
			$contribution = ContributionGet( $params );
		}
		
		// retrieve max contribution amount
		$query  = "SELECT ifnull(t.total_amount,'0.00') total_amount FROM civicrm_membership_payment p
		left join civicrm_membership m on p.membership_id=m.id
		left join civicrm_contribution t on p.contribution_id=t.id
		WHERE m.membership_type_id =" . $membership['membership_type_id'] . "
		AND m.contact_id=" . $contact_id . "
		ORDER BY t.receive_date DESC
		LIMIT 0,1";
		$params = array( );
		$select_result =& CRM_Core_DAO::executeQuery( $query, $params );
		while ( $select_result->fetch( ) ) {
			$total_amount = $select_result->total_amount;
		}
		
		// the fulfillment array
		$fulfillment_details = array(
			'amount_level'			=> $contribution['amount_level'],
			'end_date'				=> $membership['end_date'],
			'contact_id'			=> $contact_id,
			'first_name'			=> $contact['first_name'],
			'last_name'				=> $contact['last_name'],
			'organization_name'		=> $contact['current_employer'],
			'street_address'		=> $contact['street_address'],
			'supplemental_address_1'=> $contact['supplemental_address_1'],
			'city'					=> $contact['city'],
			'state_province'		=> $contact['state_province'],
			'postal_code'			=> $contact['postal_code'],
			'postal_code_suffix'	=> $contact['postal_code_suffix'],
			'membership_name'		=> $membership['membership_name'],
			'total_amount'			=> $total_amount,
			'region'				=> "IMBA US",
			'chapter'				=> "Unassigned",
			'email'					=> $contact['email'],
			'chapter_from'			=> "none",
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
	
		// organization name and primary contact name
		if ($contact['contact_type'] == "Organization") {
			$fulfillment_details['organization_name'] = $contact['display_name'];
			$organization_contact_name = organization_contact_name($contact_id);
			$fulfillment_details['first_name'] = $organization_contact_name['first_name'];
			$fulfillment_details['last_name'] = $organization_contact_name['last_name'];
		}
		
		$from = "contribution";
		if ($membership['membership_type_id'] == 1 || $membership['membership_type_id'] == 5) {
			// region and chapter logic for individual and family memberships
			// if the contribution has region/chapter, use it. 
			// if not, use the contact r/c unless there are multiple chapters selected.
			if (isset($contribution['custom_77']) && isset($chapters[$contribution['custom_77']])) {
				// chapter is in the list 
				$fulfillment_details['region']  = strtoupper($chapters[$contribution['custom_77']]);
				$fulfillment_details['chapter'] = $contribution['custom_77'];
				$fulfillment_details['chapter_from'] = $from;
			} else if (isset($contribution['custom_76']) && strtoupper($contribution['custom_76']) == 'SORBA') {
				// chapter is not in the list but region is SORBA
				$fulfillment_details['region']  = strtoupper($contribution['custom_76']);
				if (isset($contribution['custom_77'])) {
					$fulfillment_details['chapter'] = $contribution['custom_77'];
				} else{
					$fulfillment_details['chapter'] = "Unassigned";
				}
				$fulfillment_details['chapter_from'] = $from;
			} else {
				$from = "contact";
				// no region/chapter data from contribution -- check contact
				if (isset($contact['custom_80'])) {
					// chapter(s) are listed on contact
					$contact_chapters = explode(chr(01),trim($contact['custom_80'],chr(01)));
					foreach ($contact_chapters as $key => $chapter) {
						// are the chapters on the list?
						if (isset($chapters[$chapter])) {
							$membership_chapters[] = array('chapter' => $chapter, 'region' => $chapters[$chapter]);
						}
					}
					if (count($membership_chapters) == 1) {
						// we have one good chapter match
						$fulfillment_details['region']  = strtoupper($membership_chapters[0]['region']);
						$fulfillment_details['chapter'] = $membership_chapters[0]['chapter'];
						$fulfillment_details['chapter_from'] = $from;
					} else if (strtoupper($contact['custom_79']) == 'SORBA') {
						// region is sorba
						$fulfillment_details['region']  = strtoupper($contact['custom_79']);
						if ($contact['custom_80'] == "At Large") {
							$fulfillment_details['chapter'] = $contact['custom_80'];
						} else {
							$fulfillment_details['chapter'] = "Unassigned";
						}
						$fulfillment_details['chapter_from'] = $from;
					} else if (count($membership_chapters) > 1) {
						$fulfillment_details['chapter'] = "Multiple Chapters";
						$fulfillment_details['chapter_from'] = $from;
					} 
				}
			}
		} else if ($membership['membership_type_id'] == 2 || $membership['membership_type_id'] == 3) {
			// region and chapter logic for nmbp patroller and ambassador memberships
			// if the contribution has chapter, use it. 
			// if not, set to "no group".
			if (isset($contribution['custom_77']) && isset($patrol_groups[$contribution['custom_77']])) {
				$fulfillment_details['chapter'] = $contribution['custom_77'];
			} else {
				$fulfillment_details['chapter'] = "No Group";
			}
			$fulfillment_details['chapter_from'] = $from;
		}
		unset($fulfillment_details['chapter_from']);
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
		fwrite($fh, $line);
		//print_r($fulfillment_details);
		//break;
	}
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
?>
