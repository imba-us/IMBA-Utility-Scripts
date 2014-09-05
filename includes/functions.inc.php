<?php

function primary_membership($contact_id, $membership_id, $end_date, $membership_type_id) {
	$primary_membership = 1;
	// If contact with individual or family membership due for renewal also has a different membership of type individual, patroller, ambassador, family, or lifetime with a greater membership end_date, don't send renewal
	//echo $contact_id . ":" . $membership_id . ":" . $end_date . ":" . $membership_type_id;
	if ($membership_type_id == 1 || $membership_type_id == 2 || $membership_type_id == 3 || $membership_type_id == 5) {
		$query  = "SELECT id,membership_type_id,end_date FROM civicrm_membership WHERE membership_type_id IN (1,2,3,5,6) AND status_id NOT IN (5,6,7) AND end_date>'" . $end_date . "' AND contact_id=" . $contact_id . " AND id != " . $membership_id;
		//echo $query;
		$params = array( );		
		$membership_select_result =& CRM_Core_DAO::executeQuery( $query, $params );
		if ( $membership_select_result->fetch( ) ) {
			$primary_membership = 0;
			//echo "skip reminder: " . $contact_id . "\t" . $toEmail . "\t" . $membership_id . "\t" . $membership_select_result->id . "\t" . $membership_select_result->membership_type_id . "\t" . $membership_select_result->end_date . "\n";
		}
	}
	return $primary_membership;
}

function organization_contact_name($contact_id=NULL) {
	if ($contact_id) {
		$now = time();
		$today = date('Y-m-d',  mktime(0, 0, 0, date('m',$now), date('d',$now), date('Y',$now)));

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
			$organization_contact_name['first_name'] = $select_result->first_name;
			$organization_contact_name['last_name'] = $select_result->last_name;
		} else {
			$query  = "SELECT c.first_name, c.last_name, e.email FROM civicrm_contact c
			LEFT JOIN civicrm_relationship r ON c.id=r.contact_id_a
			LEFT JOIN civicrm_email e ON c.id=e.contact_id AND e.is_primary=1
			WHERE (r.end_date>='" . $today . "' or r.end_date IS NULL)
			AND r.is_active=1
			AND c.is_deleted=0
			AND r.relationship_type_id=4
			AND r.contact_id_b=" . $contact_id . "
			GROUP BY contact_id_b";
			$params = array( );		
			$select_result =& CRM_Core_DAO::executeQuery( $query, $params );
			if ( $select_result->fetch( ) ) {
				$organization_contact_name['first_name'] = $select_result->first_name;
				$organization_contact_name['last_name'] = $select_result->last_name;
				$organization_contact_name['email'] = $select_result->email;
			} else {
				$organization_contact_name['first_name'] = "Friend of IMBA";
				$organization_contact_name['last_name'] = NULL;
				$organization_contact_name['email'] = NULL;
			}
		}
		return $organization_contact_name;
	} else {
		return 0;
	}
}

function ContactGet( $params ) {
	$result =& civicrm_contact_get( $params );
	if ( civicrm_error ( $result )) {
		echo $result['error_message'];
	} else {
		return $result;
	}
}

function ContributionGet( $params ) {
	$result =& civicrm_contribution_get( $params );
	if ( civicrm_error ( $result )) {
		echo $result['error_message'];
	} else {
		return $result;
	}
}

function MembershipsGet( $params ) {
	$result =& civicrm_contact_memberships_get( $params );
	if ( civicrm_error ( $result )) {
		echo $result['error_message'];
	} else {
		return $result;
	}
}

function print_date($mysql_date_time){
	if (preg_match('/^.* .*$/', $mysql_date_time)) {
		$date_time_pieces = explode(' ', $mysql_date_time);
		$mysql_date = $date_time_pieces[0];
	} else {
		$mysql_date = $mysql_date_time;
	}
	$date_pieces = explode('-',$mysql_date);
	$date = $date_pieces[1] . '/' . $date_pieces[2] . '/' . $date_pieces[0];
	return $date;
}

function mail_attachment($filename, $path, $mailto, $from_mail, $from_name, $replyto, $subject, $message) {
    $file = $path.$filename;
    $file_size = filesize($file);
    $handle = fopen($file, "r");
    $content = fread($handle, $file_size);
    fclose($handle);
    $content = chunk_split(base64_encode($content));
    $uid = md5(uniqid(time()));
    $name = basename($file);
    $header = "From: ".$from_name." <".$from_mail.">\r\n";
    $header .= "Reply-To: ".$replyto."\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
    $header .= "This is a multi-part message in MIME format.\r\n";
    $header .= "--".$uid."\r\n";
    $header .= "Content-type:text/plain; charset=iso-8859-1\r\n";
    $header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $header .= $message."\r\n\r\n";
    $header .= "--".$uid."\r\n";
    $header .= "Content-Type: application/octet-stream; name=\"".$filename."\"\r\n"; // use different content types here
    $header .= "Content-Transfer-Encoding: base64\r\n";
    $header .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
    $header .= $content."\r\n\r\n";
    $header .= "--".$uid."--";
    if (mail($mailto, $subject, "", $header)) {
        echo "mail send ... OK\n"; // or use booleans here
    } else {
        echo "mail send ... ERROR!\n";
    }
}

// Chapters
function get_chapters() {
	$query  = "SELECT c.id, c.organization_name, c.nick_name, r.region_79 region
	FROM civicrm_contact c, civicrm_membership m, civicrm_value_revenues_16 a, civicrm_value_region_and_chapter_12 r
	WHERE c.id = m.contact_id
	AND c.id = a.entity_id
	AND c.id = r.entity_id
	AND m.membership_type_id =11
	AND m.status_id
	IN ( 1, 2 ) 
	AND a.revenue_sharing_99 =1
	AND c.is_deleted !=1
	ORDER BY c.organization_name, r.region_79";
	
	$chapters = array();
	$chapter_mismatch = 0;
	$chapter = NULL;
	$params = array( );
	$chapters_results =& CRM_Core_DAO::executeQuery( $query, $params );
	while ( $chapters_results->fetch( ) ) {
		$chapter = $chapters_results->organization_name;
		if ($chapters_results->nick_name) $chapter .= " (" . $chapters_results->nick_name . ")";
		$query  = "SELECT value FROM  civicrm_option_value WHERE  value='" . $chapter . "' AND option_group_id=45";
		$params = array( );
		$check_results =& CRM_Core_DAO::executeQuery( $query, $params );
		if ( ! $check_results->fetch( ) ) {
			echo "Chapter not found in region/chapter options: " . $chapter . " – https://www.imba.com/civicrm/contact/view?reset=1&cid=" . $chapters_results->id . "\n";
			$chapter_mismatch = 1;
			} else if ( is_null($chapters_results->region) || $chapters_results->region=='' ) {
			echo "Chapter missing region: " . $chapter . " – https://www.imba.com/civicrm/contact/view?reset=1&cid=" . $chapters_results->id . "\n";
			$chapter_mismatch = 1;
		} else {
			$chapters[$chapter] = $chapters_results->region;
		}
	}
	if ($chapter_mismatch) {
		return 0;
	} else {
		return $chapters;
	}
}
?>