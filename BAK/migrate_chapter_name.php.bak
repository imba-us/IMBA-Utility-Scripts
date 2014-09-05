<?php
// Require config file from command line argument
if (count($argv) > 1) {
	require_once $argv[1];
} else {
	echo "usage: $argv[0] [config_script].php\n";
	exit;
}

// Initialize CiviCRM
require_once '/var/www/imba/sites/all/modules/civicrm/civicrm.config.php'; 
require_once 'CRM/Core/Config.php'; 
$config =& CRM_Core_Config::singleton();
require_once 'api/v2/Contact.php';
// Initialize database for drupal
$host = 'localhost';
$user = 'imba';
$pass = 'fCxC4HGmTNxrfmR3';
$db   = 'imba_drupal';

// Check for existence of old chapter name
$query  = "
SELECT count(*) AS count 
FROM civicrm_option_value
WHERE label='". $old_name ."'
OR value='". $old_name ."'
AND option_group_id=45
";
if ($verbose) echo "$query\n";
if (!$debug) {
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	$results->fetch( );
	if (!$results->count) {
		echo "$old_name  not found in custom data field options list\n";
		echo "\nContinue (y/n)? ";
		if (trim(fgets(STDIN)) != 'y') exit;
	} else {
		// Update chapter name in custom data field options list
		$query  = "
		UPDATE civicrm_option_value
		SET  label='". $new_name ."', value='". $new_name ."'
		WHERE (
			label='". $old_name ."'
			OR value='". $old_name ."'
			)
		AND option_group_id=45
		";
		if ($verbose) echo "$query\n";
		if (!$debug) {
			$params = array( );
			$results =& CRM_Core_DAO::executeQuery( $query, $params );
			$message .= "$new_name updated in custom data field options list\n";
		}
		
		// Reset chapter option ordering
		$query  = "
		UPDATE civicrm_option_value
		SET weight=0
		WHERE option_group_id=45
		";
		if ($verbose) echo "$query\n";
		if (!$debug) {
			$params = array( );
			$results =& CRM_Core_DAO::executeQuery( $query, $params );
		}
	}
}

// Update contribution/civicrm_value_revenue_sharing_11
$query  = "
UPDATE civicrm_value_revenue_sharing_11
SET chapter_77='" . $new_name ."'
WHERE chapter_77 = '" . $old_name ."'
";
if ($verbose) echo "$query\n";
if (!$debug) {
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	$message .= "Contributions updated\n";
}

// Update contact 
$query  = "
UPDATE civicrm_value_region_and_chapter_12
SET chapter_80 = replace(chapter_80,'" . $old_name ."','" . $new_name ."') 
WHERE chapter_80 LIKE '%" . $old_name ."%'
";
if ($verbose) echo "$query\n";
if (!$debug) {
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	$message .= "Contacts updated\n";
}

// Find group titles to update
$query ="
SELECT id, title
FROM imba_civicrm.civicrm_group
WHERE title LIKE '" . $old_chapter ."%' 
";
$query .= ($old_nickname) ? "OR title LIKE '$old_nickname%'" : '';
if ($verbose) echo "$query\n";
if (!$debug) {
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	$results->fetch();
	if ($results->id) {
		echo "The following group titles will be modified:\n";
		do {
			echo $results->title . ": https://www.imba.com/civicrm/group/search?reset=1&force=1&context=smog&gid=" . $results->id . "\n";
		} while ($results->fetch());
		echo "\nUpdate (y/n)? ";
		if (trim(fgets(STDIN)) == 'y') {
			// Update group titles
			$new_title = ($new_nickname) ? $new_nickname : $new_chapter;
			$query ="
			UPDATE imba_civicrm.civicrm_group
			SET title = replace(title,'" . $old_chapter . "','" . $new_title ."'),
			title = replace(title,'" . $old_nickname . "','" . $new_title ."') 
			WHERE title LIKE '" . $old_chapter ."%' 
			";
			$query .= ($old_nickname) ? "OR title LIKE '$old_nickname%'" : '';
			if ($verbose) echo "$query\n";
			if (!$debug) {
				$params = array( );
				$results =& CRM_Core_DAO::executeQuery( $query, $params );
				$message .= "Group tiles modified\n";
			}
		}
	}
}

// Find contribution membership blocks to update
$query ="
SELECT entity_id, new_title
FROM imba_civicrm.civicrm_membership_block
WHERE new_title LIKE '%" . $old_name ."%' 
OR renewal_title LIKE '%" . $old_name ."%' 
OR new_text LIKE '%" . $old_name ."%' 
OR renewal_text LIKE '%" . $old_name ."%' 
";
if ($verbose) echo "$query\n";
if (!$debug) {
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	$results->fetch();
	if ($results->entity_id) {
		echo "The following pages will be modified:\n";
		do {
			echo $results->new_title . ": https://www.imba.com/civicrm/contribute/transact?reset=1&id=" . $results->entity_id . "\n";
		} while ($results->fetch());
		echo "\Update (y/n)? ";
		if (trim(fgets(STDIN)) == 'y') {
			// Update contribution membership blocks
			$query ="
			UPDATE imba_civicrm.civicrm_membership_block
			SET new_title = replace(new_title,'" . $old_name . "','" . $new_name ."'), 
			renewal_title = replace(renewal_title,'" . $old_name . "','" . $new_name ."'), 
			new_text = replace(new_text,'" . $old_name . "','" . $new_name ."'), 
			renewal_text = replace(renewal_text,'" . $old_name . "','" . $new_name ."') 
			WHERE new_title LIKE '%" . $old_name ."%' 
			OR renewal_title LIKE '%" . $old_name ."%' 
			OR new_text LIKE '%" . $old_name ."%' 
			OR renewal_text LIKE '%" . $old_name ."%' 
			";
			if ($verbose) echo "$query\n";
			if (!$debug) {
				$params = array( );
				$results =& CRM_Core_DAO::executeQuery( $query, $params );
				$message .= "Contribution membership blocks modified\n";
			}
		}
	}
}

// Find contribution pages to update
$query ="
SELECT id, title
FROM imba_civicrm.civicrm_contribution_page
WHERE title LIKE '%" . $old_name ."%' 
OR intro_text LIKE '%" . $old_name ."%' 
OR thankyou_title LIKE '%" . $old_name ."%' 
OR thankyou_text LIKE '%" . $old_name ."%' 
OR receipt_text LIKE '%" . $old_name ."%' 
";
if ($verbose) echo "$query\n";
if (!$debug) {
	$params = array( );
	$results =& CRM_Core_DAO::executeQuery( $query, $params );
	$results->fetch();
	if ($results->id) {
		echo "The following pages will be modified:\n";
		do {
			echo $results->title . ": https://www.imba.com/civicrm/contribute/transact?reset=1&id=" . $results->id . "\n";
		} while ($results->fetch());
		echo "\Update (y/n)? ";
		if (trim(fgets(STDIN)) == 'y') {
			// Update contribution pages
			$query ="
			UPDATE imba_civicrm.civicrm_contribution_page
			SET title = replace(title,'" . $old_name . "','" . $new_name ."'), 
			intro_text = replace(intro_text,'" . $old_name . "','" . $new_name ."'), 
			thankyou_title = replace(thankyou_title,'" . $old_name . "','" . $new_name ."'), 
			thankyou_text = replace(thankyou_text,'" . $old_name . "','" . $new_name ."'),
			receipt_text = replace(receipt_text,'" . $old_name . "','" . $new_name ."') 
			WHERE title LIKE '%" . $old_name ."%' 
			OR intro_text LIKE '%" . $old_name ."%' 
			OR thankyou_title LIKE '%" . $old_name ."%' 
			OR thankyou_text LIKE '%" . $old_name ."%' 
			OR receipt_text LIKE '%" . $old_name ."%' 
			";
			if ($verbose) echo "$query\n";
			if (!$debug) {
				$params = array( );
				$results =& CRM_Core_DAO::executeQuery( $query, $params );
				$message .= "Contribution pages modified\n";
			}
		}
	}
}

// Find nodes to update
$query ="
SELECT nid
FROM imba_drupal.node_revisions
WHERE body LIKE '%" . $old_name ."%' 
GROUP by nid
";
if ($verbose) echo "$query\n";
if (!$debug) {
	$link = mysql_connect($host, $user, $pass) or die("Can not connect." . mysql_error());
	mysql_select_db($db) or die("Can not connect.");
	$results = mysql_query($query);
	$row = mysql_fetch_assoc($results);
	if ($row['nid']) {
		echo "The following Drupal nodes will be modified:\n";
		do {
			echo "http://www.imba.com/node/" . $row['nid'] . "\n";
		} while ($row = mysql_fetch_assoc($results));
		echo "\nContinue (y/n)? ";
		if (trim(fgets(STDIN)) == 'y') {
			// Update drupal nodes
			mysql_free_result($results);
			$query ="
			UPDATE imba_drupal.node_revisions 
			SET body = replace(body,'" . $old_name . "','" . $new_name ."'), 
			teaser = replace(teaser,'" . $old_name . "','" . $new_name ."')
			WHERE body LIKE '%" . $old_name ."%' 
			";
			if ($verbose) echo "$query\n";
			if (!$debug) {
				$link = mysql_connect($host, $user, $pass) or die("Can not connect." . mysql_error());
				mysql_select_db($db) or die("Can not connect.");
				$results = mysql_query($query);
				if ($results) $message .= "Drupal nodes modified\n";
			}
		}
	}
}

// Output results
if ($email) mail($recipient, $subject . " (post)", $message);
echo "$message
Chapter name migration complete

Next steps:

– Update civi_tracker module

";

//SELECT *  FROM `node_revisions` WHERE `nid` = 950 order by timestamp desc limit 1


?>