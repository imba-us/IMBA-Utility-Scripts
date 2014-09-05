<?php
// Initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton(); 
require_once "api/v2/Contact.php";
require_once "api/v2/Contribute.php";
require_once "api/v2/Membership.php";
require_once '/usr/local/bin/imba/includes/functions.inc.php';

// Variable Declarations
$now = time();
$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));
$path = '/var/local/imba/renewals/';

// print <br /> or \n depending on environment
function println($string_message = '') {
	return isset($_SERVER['SERVER_PROTOCOL']) ? print "$string_message<br />" . PHP_EOL:print $string_message . PHP_EOL;
}

// check for arguments
if (count($argv) <= 1) {
	println("Please specify contacts to merge in following format: contact_id_to_keep:contact_id_to_merge");
	exit;
}

// loop through contacts to merge
array_shift($argv);
foreach ( $argv as $key => $value ) {
	// debug
	println($argv[$key]);

	// reset variables for loop
	$contact_ids = array();
	$save_id = NULL;
	$delete_id = NULL;

	// split contact_ids
	$contact_ids = array();
	$contact_ids = explode(":", $argv[$key]);
	if (!is_numeric($contact_ids[0]) || !is_numeric($contact_ids[1])) {
		println("Contact ids not in valid numeric format");
		continue 1;
	} else {
		$save_id = $contact_ids[0];
		$delete_id = $contact_ids[1];
	}
	
	// do contact_ids exist?
	$query = "SELECT count(*) as count from civicrm_contact WHERE id IN (" . $save_id . ", ". $delete_id . ")";
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	while ($results->fetch( )) {
		if ($results->count != 2) {
			println("Contact " . $save_id . " or " . $delete_id . " not found");
			continue 2;
		}
	}
	
	// are both contact_ids same contact type?
	$query = "SELECT count(*) as count from civicrm_contact WHERE id IN (" . $save_id . ", ". $delete_id . ") GROUP BY contact_type LIMIT 0,1";
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	while ($results->fetch( )) {
		if ($results->count <= 1) {
			println("Contacts " . $save_id . " and " . $delete_id . " must both be the same contact type");
			continue 2;
		}
	}
	
	// move contribution(s)
	$query = "UPDATE civicrm_contribution SET contact_id=" . $save_id . " WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);

	// move address(es)
	/*
	$query = "UPDATE civicrm_address SET is_primary=0, is_billing=0 WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	$query = "UPDATE civicrm_address SET contact_id=" . $save_id . " WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	*/

	// move email(s)
	$query = "UPDATE civicrm_email SET is_primary=0, is_billing=0 WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	$query = "UPDATE civicrm_email SET contact_id=" . $save_id . " WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	
	// move notes(s)
	$query = "UPDATE civicrm_note SET entity_id=" . $save_id . " WHERE entity_table='civicrm_contact' AND entity_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);

	// move relationship(s)
	$query = "UPDATE civicrm_relationship SET contact_id_a=" . $save_id . " WHERE contact_id_a=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	$query = "UPDATE civicrm_relationship SET contact_id_b=" . $save_id . " WHERE contact_id_b=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);

	// move event registration(s)
	$query = "UPDATE civicrm_participant SET contact_id=" . $save_id . " WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);

	// move subaru vip requests(s)
	$query = "UPDATE civicrm_value_subaru_vip_program_8 SET entity_id=" . $save_id . " WHERE entity_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);

	// move tags(s)
	$query = "UPDATE civicrm_entity_tag SET contact_id=" . $save_id . " WHERE contact_id=" . $delete_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);

	// move region & chapter(s)
	$query = "SELECT count(*) as count FROM civicrm_value_region_and_chapter_12 WHERE entity_id=" . $save_id;
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	while ($results->fetch( )) {
		if ($results->count == 0) {
			$query = "UPDATE civicrm_value_region_and_chapter_12 SET entity_id=" . $save_id . " WHERE entity_id=" . $delete_id;
			$params = array( );
			$update =& CRM_Core_DAO::executeQuery( $query, $params );
			println($query);
		}
	}
	
	// move or merge memberships
	$query = "SELECT count(*) as count, membership_type_id, min(join_date) as min_join_date, min(start_date) as min_start_date, max(end_date) as max_end_date from civicrm_membership WHERE contact_id IN (" . $save_id . ", ". $delete_id . ") GROUP BY membership_type_id";
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	println($query);
	while ($results->fetch( )) {
		if ($results->count > 1) {
			// find membership to save
			$query = "SELECT id from civicrm_membership WHERE contact_id=". $save_id . " AND membership_type_id=" . $results->membership_type_id . " ORDER BY end_date DESC LIMIT 0,1";
			$params = array( );
			$save_membership =& CRM_Core_DAO::executeQuery( $query, $params );
			if ($save_membership->fetch( )) {
				// find membership(s) to delete
				$query = "SELECT id from civicrm_membership WHERE contact_id=". $delete_id . " AND membership_type_id=" . $results->membership_type_id;
				$params = array( );
				$delete_membership =& CRM_Core_DAO::executeQuery( $query, $params );
				while ($delete_membership->fetch( )) {
					// move membership payments
					$query = "UPDATE civicrm_membership_payment SET membership_id=" . $save_membership->id . " WHERE membership_id=" . $delete_membership->id;
					$params = array( );
					$update =& CRM_Core_DAO::executeQuery( $query, $params );
					println($query);
				}
				// merge membership dates
				$query = "UPDATE civicrm_membership SET join_date='" . $results->min_join_date . "', start_date='" . $results->min_start_date . "', end_date='" . $results->max_end_date . "', status_id IS NULL WHERE id=" . $save_membership->id;
				$params = array( );
				$update =& CRM_Core_DAO::executeQuery( $query, $params );
				println($query);
			} else {
				println("No membership found to save.");
			}
		} else {
			$query = "UPDATE civicrm_membership SET contact_id=" . $save_id . " WHERE contact_id=" . $delete_id . " AND membership_type_id=" . $results->membership_type_id;
			$params = array( );
			$results =& CRM_Core_DAO::executeQuery( $query, $params );
			println($query);
		}
	}
}

?>