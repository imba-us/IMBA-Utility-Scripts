<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.0                                               |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2009                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/*
 * This file checks and updates the status of all membership records for a given domain using the calc_membership_status and
 * update_contact_membership APIs.
 * It takes the first argument as the domain-id if specified, otherwise takes the domain-id as 1.
 *
 * IMPORTANT: You must set a valid FROM email address on line 199 before and then save the file as
 * UpdateMembershipRecord.php prior to running this script.
 */

class CRM_Membership_Renewals_Email {

    function __construct()
    {
        $this->initialize( );

        $config = CRM_Core_Config::singleton();

        // this does not return on failure
        //CRM_Utils_System::authenticateScript( true );
    }

    function initialize( ) {
		require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php';
		require_once '/var/www/imba/sites/all/modules/civicrm/CRM/Core/Config.php';

        $config = CRM_Core_Config::singleton();

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
        require_once '/usr/local/bin/imba/includes/functions.inc.php';
    }

    public function send_membership_reminders_email( $segment ) {
		// output diag but don't send
		$test = 0;

		// set config values
		$config = CRM_Core_Config::singleton();
        $allStatus    = CRM_Member_PseudoConstant::membershipStatus( );
        $statusLabels = CRM_Member_PseudoConstant::membershipStatus( null, null, 'label' );
        $allTypes     = CRM_Member_PseudoConstant::membershipType( );

		// define which civicrm message template to use
		// segment
		if ($segment == 'r') {
			$membership_type_ids = "14,15,16";
			/*
			ICP Level 1 Certification	14
			ICP Level 2 Certification	15
			ICP Level 3 Certification	16
			*/
			$icp_template = '232';
			$end_date_interval ="
AND    civicrm_membership.end_date >= date(now())
AND    civicrm_membership.end_date < date(now() + INTERVAL 2 MONTH)";
		} else {
			$membership_type_ids = "14,15,16";
			$icp_template = '233';
			$end_date_interval ="
AND    civicrm_membership.end_date >= date(now() - INTERVAL 2 MONTH)
AND    civicrm_membership.end_date < date(now() - INTERVAL 1 DAY)";
		}

        //get all active statuses of membership, CRM-3984
        $allTypes  = CRM_Member_PseudoConstant::membershipType( );

		$query = "
SELECT civicrm_membership.id                 as membership_id,
       civicrm_membership.is_override        as is_override,
       civicrm_membership.reminder_date      as reminder_date,
       civicrm_membership.membership_type_id as membership_type_id,
       civicrm_membership.status_id          as status_id,
       civicrm_membership.join_date          as join_date,
       civicrm_membership.start_date         as start_date,
       civicrm_membership.end_date           as end_date,
       civicrm_membership.source             as source,
       civicrm_contact.id                    as contact_id,
       civicrm_contact.is_deceased           as is_deceased,
       civicrm_membership.owner_membership_id as owner_membership_id,
       civicrm_value_region_and_chapter_12.region_79 as region,
       civicrm_value_region_and_chapter_12.chapter_80 as chapter,
       civicrm_address.state_province_id     as state
FROM   civicrm_contact
LEFT JOIN civicrm_address ON civicrm_contact.id=civicrm_address.contact_id AND civicrm_address.is_primary=1
LEFT JOIN civicrm_membership ON civicrm_contact.id=civicrm_membership.contact_id
LEFT JOIN civicrm_value_region_and_chapter_12 ON civicrm_contact.id=civicrm_value_region_and_chapter_12.entity_id
LEFT JOIN civicrm_value_campaign_preferences_10 ON civicrm_contact.id=civicrm_value_campaign_preferences_10.entity_id
WHERE  civicrm_membership.status_id NOT IN (5,6,7)
AND    (civicrm_value_campaign_preferences_10.send_renewals_74 = 1 or civicrm_value_campaign_preferences_10.send_renewals_74 IS NULL)
AND    civicrm_membership.is_test = 0
AND    civicrm_membership.owner_membership_id IS NULL
AND    civicrm_membership.membership_type_id IN (" . $membership_type_ids . ")
AND    civicrm_contact.is_deleted = 0
AND    civicrm_contact.is_deceased = 0
AND    civicrm_contact.do_not_email = 0
" . $end_date_interval . "
ORDER BY civicrm_contact.id";
		//echo $query; exit;
        $params = array( );
        $dao =& CRM_Core_DAO::executeQuery( $query, $params );

        // statistics
        $sent = 0;
        $processed = 0;

        require_once '/var/www/imba/sites/all/modules/civicrm/CRM/Core/Smarty.php';
        $smarty =& CRM_Core_Smarty::singleton();
		if (!$test) {
			echo "Sending renewals...\n";
		} else {
			echo "Testing renewals...\n";
		}

        while ( $dao->fetch( ) ) {
            //send reminder for membership renewal
			$toEmail  = CRM_Contact_BAO_Contact::getPrimaryEmail( $dao->contact_id );
			$processed++;
			if ( !$toEmail ) {
				echo $processed . " " . $dao->contact_id . ": " . $dao->end_date . ": no email\n";
			} else {
				echo $processed . " " . $dao->contact_id . ": " . $dao->end_date . ": sending renewal for type " . $allTypes[$dao->membership_type_id] . " with template ";

                // Put common parameters into array for easy access
                $memberParams = array( 'id'                 => $dao->membership_id,
                                   'status_id'          => $dao->status_id,
                                   'contact_id'         => $dao->contact_id,
                                   'membership_type_id' => $dao->membership_type_id,
                                   'membership_type'    => $allTypes[$dao->membership_type_id],
                                   'join_date'          => $dao->join_date,
                                   'start_date'         => $dao->start_date,
                                   'end_date'           => $dao->end_date,
                                   'reminder_date'      => $dao->reminder_date,
                                   'source'             => $dao->source,
                                   'skipStatusCal'      => true,
                                   'skipRecentView'     => true );

                $smarty->assign_by_ref('memberParams', $memberParams);

				// Set the FROM email address for reminder emails here.
				// This must be a valid account for your SMTP service.
				$from = "tammy@imba.com";

				$message_template = NULL;
				switch($allTypes[$dao->membership_type_id]) {
					case "ICP Level 1 Certification":
					case "ICP Level 2 Certification":
					case "ICP Level 3 Certification":
						$message_template = $icp_template;
						break;
					default:
						$message_template = $icp_template;
						break;
				}
				echo $message_template . "\n";
				//print_r($dao);
				if (!$test) {
					if ($message_template) {
						// Send email renewal reminder
						if (primary_membership($dao->contact_id, $dao->membership_id, $dao->end_date, $dao->membership_type_id)) {
							//echo "send reminder: $dao->contact_id\t$dao->membership_id\t$toEmail\t$message_template\t" . $allTypes[$dao->membership_type_id] . "\n";
							$result = CRM_Core_BAO_MessageTemplates::sendReminder( $dao->contact_id, $toEmail, $message_template, $from );
							if ( ! $result || is_a( $result, 'PEAR_Error' ) ) {
								// we could not send an email, for now we ignore
								// CRM-3406
								// at some point we might decide to do something
								echo "\nCould not send email to $dao->contact_id\n";
								//print_r($result);
							} else {
								// insert the activity log record.
								$sent++;
								$activityParams = array( );
								$activityParams['subject']            = $allTypes[$dao->membership_type_id] .
									", End Date - " . CRM_Utils_Date::customFormat(CRM_Utils_Date::isoToMysql($dao->end_date), $config->dateformatFull);
								$activityParams['source_record_id']   = $dao->membership_id;
								$activityParams['source_contact_id']  = $dao->contact_id;
								$activityParams['activity_date_time'] = date('YmdHis');

								static $actRelIds = array( );
								if ( ! isset($actRelIds['activity_type_id']) ) {
									$actRelIds['activity_type_id']    =
										CRM_Core_OptionGroup::getValue( 'activity_type',
																		'Membership Renewal Reminder', 'name' );
								}
								$activityParams['activity_type_id']   = $actRelIds['activity_type_id'];

								if ( ! isset($actRelIds['activity_status_id']) ) {
									$actRelIds['activity_status_id']  =
										CRM_Core_OptionGroup::getValue( 'activity_status', 'Completed', 'name' );
								}
								$activityParams['status_id']          = $actRelIds['activity_status_id'];
								$activityParams['details'] = "Renewal message sent.";
								$activity = CRM_Activity_BAO_Activity::create( $activityParams );
							}
						} else {
							// not primary membership
						}
					} else {
						echo "WARNING: No message template for contact id: $dao->contact_id, membership type id: $dao->membership_type_id\n";
					}
				}
			}
            // CRM_Core_Error::debug( 'fEnd', count( $GLOBALS['_DB_DATAOBJECT']['RESULTS'] ) );
        }
        return array($processed, $sent);
    }
}

// Main
$segment = $argv[1];

if ($segment) {
	$obj =& new CRM_Membership_Renewals_Email( );
	list($processed, $sent) = $obj->send_membership_reminders_email( $segment );
	echo "\n$processed membership renewals processed. $sent membership renewals sent. (Done) \n";
} else {
	echo "Please specify segment 'r' or 'l'\n";
}
