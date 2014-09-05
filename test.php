<?php
// Initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton();
require_once 'CRM/Member/BAO/MembershipLog.php';
require_once 'CRM/Member/BAO/Membership.php';
require_once 'CRM/Core/BAO/MessageTemplates.php';
require_once 'CRM/Member/BAO/MembershipType.php';
require_once 'CRM/Utils/Date.php';
require_once 'CRM/Utils/System.php';
require_once 'api/v2/Membership.php';
require_once 'CRM/Member/PseudoConstant.php';
require_once 'CRM/Contact/BAO/Contact.php';
require_once 'CRM/Activity/BAO/Activity.php';

$end_date = "20200101";
$contact_id = "200000";

$query  = "SELECT id,membership_type_id,end_date FROM civicrm_membership WHERE membership_type_id IN (1,2,3,5,6) AND end_date>" . $end_date . " AND contact_id=" . $contact_id;
$params = array( );		
$membership_select_result =& CRM_Core_DAO::executeQuery( $query, $params );
if ( !$membership_select_result->fetch( ) ) {
	$membership_id = $membership_select_result->id;
	echo $membership_id . "\n";
}

?>