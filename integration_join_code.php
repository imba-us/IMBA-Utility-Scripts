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
require_once "api/v2/Contact.php";



// Account for chapters without nicknames
if(!$nickname) $nickname = $name;

$message = "// CiviTracker Code
					case xx:	
						\$_GET['custom_76'] = \"" . $region . "\";
						\$_GET['custom_77'] = \"" . $chapter . "\";
						break;

// Join Page Array
	'" . $nickname . "'\t=> array(
					 'fam'    => xx, 
					 'ind'    => xx, 
					 'region' => '" . $region . "', 
					 'name'   => '" . $chapter . "'),

// Individual Link
		<option class=\"xx\" value=\"<?=\$url['" . $nickname . "']['ind']?>\"><?=\$url['" . $nickname . "']['name']?></option>
		
// Retailer Link
		<option class=\"xx\" value=\"<?=\$retail_url . '&custom_76=" . $region . "&custom_77=" . $chapter . "'?>\">" . $chapter . "</option>
";

// output
echo $message;

// Write stats to file
$outfile = getcwd() . "/integration_join_code-$nickname-" .date('Ymd-U') . ".txt";
$fh = fopen($outfile, 'w') or die("\nCan't open file $outfile");
fwrite($fh, $message);
fclose($fh);

?>