<?php

/**
 * @file convert_ibiz.php
 * @brief Moves data from iBiz directory into mySQL database
 * 
 * @version 0.9
 * @author Parker Mills
 */


/*
  Important info for this script:
  -Queries are sent to mySQL in batches so that things are not slow. A batch can exceed 2 MB in size, so the mySQL 
   variable (on the server) named 'max_allowed_packet' must be larger than 6 MB.
  
  -The 3rd-party library 'CFPropertyList' cannot read in some characters. One character, ^P, can be present in iBiz files, so these characters will 
  need to be removed for all records to import.

  -This script adds the column 'addressBookId' to the Customers table, and 'uniqueIdentifier' to the Projects and Invoices tables. These are
  iBiz-specific and used to fill in the database, so they can optionally be removed after import is complete.
  
*/


/* Preferences */
$pref_mysql_server = 'localhost'; // e.g. localhost or 122.133.144.155
$pref_mysql_username = 'user';
$pref_mysql_password = 'pass';
$pref_mysql_db = 'iBizData';

$pref_ibiz_dir = './iBiz';
$pref_address_book = 'AddressBook.vcf';


/* Error Reporting */
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors in web browser
ini_set('html_errors', 1);

/* Includes */
require_once(dirname(__FILE__) . '/CFPropertyList/CFPropertyList.php'); // For reading in Apple plist files

/* Connect to mySQL database */
$db = new mysqli($pref_mysql_server, $pref_mysql_username, $pref_mysql_password);
if($db->connect_errno)
  die("Database unavailable.");


/* Begin Output */
echo "<pre>";



/*** Create Database structure ***/

/* Create the database */
$db->query("CREATE DATABASE IF NOT EXISTS `{$pref_mysql_db}`");
echo $db->error;
$db->query("USE `{$pref_mysql_db}`;");
echo $db->error;

/* Create Customers table */
$db->query("CREATE TABLE IF NOT EXISTS `Customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `NameFirst` varchar(50) DEFAULT NULL,
  `NameLast` varchar(50) DEFAULT NULL,
  `IsCompany` tinyint(1) DEFAULT NULL,
  `Company` varchar(50) DEFAULT NULL,
  `Street` varchar(50) DEFAULT NULL,
  `City` varchar(50) DEFAULT NULL,
  `State` char(2) DEFAULT NULL,
  `Zip` varchar(9) DEFAULT NULL,
  `Phone` varchar(15) DEFAULT NULL,
  `Phone2` varchar(15) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `addressBookId` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
echo $db->error;

/* Create Invoices table */
$db->query("CREATE TABLE IF NOT EXISTS `Invoices` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `invoiceNumber` int(11) DEFAULT NULL,
  `Customer` int(11) DEFAULT NULL,
  `ProjectNum` int(11) DEFAULT NULL,
  `ProjectNum2` int(11) DEFAULT NULL,
  `Amount` varchar(11) DEFAULT NULL,
  `Balance` varchar(11) DEFAULT NULL,
  `Overdue` int(11) DEFAULT NULL,
  `Date` varchar(30) DEFAULT NULL,
  `DateDue` varchar(30) DEFAULT NULL,
  `isEstimate` int(11) DEFAULT NULL,
  `uniqueIdentifier` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
echo $db->error;

/* Create JobEvents table */
$db->query("CREATE TABLE IF NOT EXISTS `JobEvents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ProjectID` int(11) DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Type` varchar(50) DEFAULT '',
  `Rate` float(11,2) DEFAULT NULL,
  `Quantity` float(11,2) DEFAULT NULL,
  `TaxRate` float(5,3) DEFAULT NULL,
  `Description` text,
  PRIMARY KEY (`id`),
  KEY `ProjectID` (`ProjectID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
echo $db->error;

/* Create Projects table */
$db->query("CREATE TABLE IF NOT EXISTS `Projects` (
  `ProjectNum` int(11) NOT NULL AUTO_INCREMENT,
  `Customer` int(11) DEFAULT NULL,
  `Title` varchar(100) DEFAULT NULL,
  `Description` text,
  `DateCreated` bigint(11) unsigned DEFAULT NULL,
  `DateModified` bigint(11) DEFAULT NULL,
  `DateDue` bigint(11) DEFAULT NULL,
  `Status` int(4) DEFAULT NULL,
  `hasPowercord` tinyint(1) DEFAULT NULL,
  `Accessories` varchar(50) DEFAULT NULL,
  `Computer` int(11) DEFAULT NULL,
  `uniqueIdentifier` varchar(50) DEFAULT NULL,
  UNIQUE KEY `ProjectNum` (`ProjectNum`),
  KEY `Customer` (`Customer`),
  KEY `Status` (`Status`),
  KEY `Computer` (`Computer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
echo $db->error;

/* Create JobEventsEstimates table */
$db->query("CREATE TABLE IF NOT EXISTS `JobEventsEstimates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ProjectID` int(11) DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `Type` varchar(50) DEFAULT '',
  `Rate` float(11,2) DEFAULT NULL,
  `Quantity` float(11,2) DEFAULT NULL,
  `TaxRate` float(5,3) DEFAULT NULL,
  `Description` text,
  PRIMARY KEY (`id`),
  KEY `ProjectID` (`ProjectID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
echo $db->error;

/* Empty tables */
/* Use this code if you want to start with all your tables empty */
/*
$db->query("TRUNCATE TABLE Customers");
echo $db->error;
$db->query("TRUNCATE TABLE Invoices");
echo $db->error;
$db->query("TRUNCATE TABLE JobEvents");
echo $db->error;
$db->query("TRUNCATE TABLE Projects");
echo $db->error;
$db->query("TRUNCATE TABLE JobEventsEstimates");
echo $db->error;
*/



/* Import Clients from iBiz */
echo "**** Importing Clients **** \n";

$plist = new CFPropertyList($pref_ibiz_dir .'/clients', CFPropertyList::FORMAT_XML);
$customerarray = $plist->toArray();
$customerarray = $customerarray['clients'];

$multi_query = '';

/* For each client */
foreach($customerarray as $customer){

  if(!empty($customer['clientCompany']))
    $clientCompany = $db->real_escape_string($customer['clientCompany']);
  else
    $clientCompany = NULL;

  if(!empty($customer['firstName']))
    $firstName = $db->real_escape_string($customer['firstName']);
  else
    $firstName = NULL;

  if(!empty($customer['lastName']))
    $lastName = $db->real_escape_string($customer['lastName']);
  else
    $lastName = NULL;
  
  $multi_query .= "INSERT INTO Customers (NameFirst, NameLast, addressBookId) VALUES ('$firstName','$lastName','" . $customer['addressBookId'] . "');\n";
  if(!empty($clientCompany))
    $multi_query .= "UPDATE Customers SET IsCompany='1',Company='{$clientCompany}' WHERE id=LAST_INSERT_ID() LIMIT 1;\n";
  else
    $multi_query .= "UPDATE Customers SET IsCompany='0' WHERE id=LAST_INSERT_ID() LIMIT 1;\n";
}
echo 'SQL Query is ' . number_format(strlen($multi_query)/1000000,1) . " MB\n";
$db->multi_query($multi_query); while ($db->more_results()) {$db->next_result(); $db->store_result();} $multi_query = ''; // run and flush multi_query

/* Import client contact information from Apple Address Book */
$address_book = file($pref_address_book);
if(!is_array($address_book))
  die("Failure while opening and reading address book file");

$simple_result = array();
$detailed_result = array();
$vcards = array();
$building_vcard = false;

/* For every line in the address book */
foreach ($address_book as $line) {
  
  /* Parse this line */
  list($parameter, $value) = explode(':', $line, 2);
  $parameter = trim($parameter);
  $value = trim($value);

  /* If a vcard is beginning */
  if(($parameter == 'BEGIN') && ($value == 'VCARD')){
    $vcard = array();
    $building_vcard = true;
  }
  
  /* If a vcard is ending */
  elseif(($parameter == 'END') && ($value == 'VCARD')){
    $vcards["{$vcard['addressBookId']}"] = $vcard;
    $building_vcard = false;

    /* Send select information from this vcard to database */
    if(!empty($vcard['EMAIL']))
      $multi_query .= "UPDATE Customers SET Email='".$db->real_escape_string($vcard['EMAIL'])."' WHERE addressBookId='{$vcard['addressBookId']}' LIMIT 1;\n";

    /* Telephone */
    if(!empty($vcard['TEL'])){
      $TEL = '';
      for($i=0; $i<strlen($vcard['TEL']); $i++){
	if(is_numeric($vcard['TEL'][$i])) $TEL .= $vcard['TEL'][$i];
      }
      $TEL = $db->real_escape_string($TEL);
      $multi_query .= "UPDATE Customers SET Phone='{$TEL}' WHERE addressBookId='{$vcard['addressBookId']}' LIMIT 1;\n";
    }
    
    /* Address */
    if(!empty($vcard['ADR'])){
      if(!empty($vcard['ADR'][2]))
	$street = $db->real_escape_string($vcard['ADR'][2]);
      else
	$street = NULL;
      
      if(!empty($vcard['ADR'][3]))
	$city = $db->real_escape_string($vcard['ADR'][3]);
      else
	$city = NULL;
      
      if(!empty($vcard['ADR'][4]))
	$state = $db->real_escape_string($vcard['ADR'][4]);
      else
	$state = NULL;

      if(!empty($vcard['ADR'][5]))
	$zip = $db->real_escape_string($vcard['ADR'][5]);
      else
	$zip = NULL;

      $multi_query .= "UPDATE Customers SET Street='{$street}', City='{$city}', State='{$state}', Zip='{$zip}' WHERE addressBookId='{$vcard['addressBookId']}' LIMIT 1;\n";
    }
  }

  /* If this is a vcard's contents */
  elseif($building_vcard){
    $parameter_p = '';
    $parameter_v = '';
    $value_p = '';
    $value_v = '';
    
    if(strpos($parameter, ';') !== false){
      $parameter = array_filter(explode(';', $parameter));
    }
    if(strpos($value, ';') !== false){
      $value = array_filter(explode(';', $value));
    }
    
    /* Decide what to do with each parameter type */
    switch($parameter){
    case('X-ABUID'):
      //list($addressBookId, $AB) = explode(':', $value);
      //$vcard['addressBookId'] = $addressBookId;
      $vcard['addressBookId'] = $value;
      break;
    case('VERSION'):
      break;
    case('PRODID'):
      break;
    case('CATEGORIES'):
      break;
    case('N'):
      if(count($value) == 2){
	$vcard['NameFirst'] = $value[1];
	$vcard['NameLast'] = $value[0];
      }
      else
	$vcard['N'] = $value;
      break;
    case('FN'):
      $vcard['full_name'] = $value;
      break;
    default:
      if($parameter[0] == 'TEL')
	$vcard['TEL'] = $value;
      elseif($parameter[0] == 'ADR')
	$vcard['ADR'] = $value;
      elseif($parameter[0] == 'EMAIL')
	$vcard['EMAIL'] = trim($value);
      else
	array_push($vcard, array($parameter, $value));
    }
  }
  
  else
    print_r($line);

}

/* Send to database */
echo 'SQL Query is ' . number_format(strlen($multi_query)/1000000,1) . " MB\n";
$db->multi_query($multi_query); while ($db->more_results()) {$db->next_result(); $db->store_result();} $multi_query = ''; // run and flush multi_query
echo "**** END: Importing Clients **** \n\n";







/* Import Projects, Jobs, and Job Estimates */
echo "**** Importing Projects, Jobs, and Job Estimates **** \n";

/* Open projects directory */
if($handle = opendir($pref_ibiz_dir . '/Projects')){

  /* For each item in directory */
  while (false !== ($filename = readdir($handle))){

    /* If this is a file */
    if(substr($filename, 0, 1) != '.'){

      /* Load this project into array */
      $plist = new CFPropertyList($pref_ibiz_dir . '/Projects/' . $filename, CFPropertyList::FORMAT_XML);
      $project = $plist->toArray();

      /* Report if client not found for this project */
      /*
      $result = $db->query("SELECT * FROM Customers WHERE addressBookId='" . $project['clientIdentifier'] . "' LIMIT 1");
      echo $db->error;
      if($result){
	$customer = $result->fetch_array();
	if(empty($customer['addressBookId']))
	  echo "\n\n!!! No customer found for Project !!!\n\n";
      }
      */

      /* Clean project strings */
      if(!empty($project['projectName']))	    $projectName = $db->real_escape_string(trim($project['projectName']));            else $projectName = NULL;
      if(!empty($project['projectNotes']))	    $projectNotes = $db->real_escape_string(trim($project['projectNotes']));          else $projectNotes = NULL;
      if(!empty($project['lastModifiedDate']))  $lastModifiedDate = $db->real_escape_string($project['lastModifiedDate'] * 1000); else $lastModifiedDate = NULL;
      if(!empty($project['projectDueDate']))    $projectDueDate = $db->real_escape_string($project['projectDueDate'] * 1000);     else $projectDueDate = NULL;
      if(!empty($project['projectStartDate']))  $projectStartDate = $db->real_escape_string($project['projectStartDate'] * 1000); else $projectStartDate = NULL;
      if(!empty($project['uniqueIdentifier']))  $uniqueIdentifier = $db->real_escape_string($project['uniqueIdentifier']);        else $uniqueIdentifier = NULL;
      if(!empty($project['projectStatus']))     $projectStatus = $db->real_escape_string($project['projectStatus']);              else $projectStatus = NULL;

      /* Construct query */
      $multi_query .= "INSERT INTO Projects (Title, Description, DateModified, DateDue, DateCreated, uniqueIdentifier, Status, Customer) VALUES ('{$projectName}','{$projectNotes}','{$lastModifiedDate}', '{$projectDueDate}', '{$projectStartDate}', '{$uniqueIdentifier}', '{$projectStatus}',  (SELECT id FROM Customers where addressBookId='{$project['clientIdentifier']}')   );\n";
      
      /* Process job events for this project */
      foreach($project['jobEvents'] as $job_event){

	/* Clean job event strings */
	if(!empty($job_event['jobEventName']))    $jobEventName = $db->real_escape_string($job_event['jobEventName']);   else $jobEventName = NULL;
	if(!empty($job_event['jobEventNotes']))	  $jobEventNotes = $db->real_escape_string($job_event['jobEventNotes']); else $jobEventNotes = NULL;
	if(!empty($job_event['jobEventRate']))    $jobEventRate = $db->real_escape_string($job_event['jobEventRate']);   else $jobEventRate = NULL;
	if(!empty($job_event['tax1']))            $tax1 = $db->real_escape_string($job_event['tax1']);                   else $tax1 = NULL;
	if(!empty($job_event['quantity']))        $quantity = $db->real_escape_string($job_event['quantity']);           else $quantity = NULL;

	/* Construct job event query */
	$multi_query .= "INSERT INTO JobEvents (ProjectID, Name, Description, Rate, TaxRate, Quantity) VALUES ((SELECT ProjectNum FROM Projects WHERE uniqueIdentifier='{$uniqueIdentifier}'), '{$jobEventName}', '{$jobEventNotes}', '{$jobEventRate}', '{$tax1}', '{$quantity}');\n";
      }

      /* Process job event estimates for this project */
      foreach($project['estimateJobEvents'] as $estimate_job_event){

	/* Clean job event estimates strings */
	if(!empty($estimate_job_event['jobEventName']))  $jobEventName = $db->real_escape_string($estimate_job_event['jobEventName']);    else $jobEventName = NULL;
	if(!empty($estimate_job_event['jobEventNotes'])) $jobEventNotes = $db->real_escape_string($estimate_job_event['jobEventNotes']);  else $jobEventNotes = NULL;
	if(!empty($estimate_job_event['jobEventRate']))  $jobEventRate = $db->real_escape_string($estimate_job_event['jobEventRate']);    else $jobEventRate = NULL;
	if(!empty($estimate_job_event['tax1']))          $tax1 = $db->real_escape_string($estimate_job_event['tax1']);                    else $tax1 = NULL;
	if(!empty($estimate_job_event['quantity']))      $quantity = $db->real_escape_string($estimate_job_event['quantity']);            else $quantity = NULL;

	/* Construct job event estimates query */
	$multi_query .= "INSERT INTO JobEventsEstimates (ProjectID, Name, Description, Rate, TaxRate, Quantity) VALUES ((SELECT ProjectNum FROM Projects WHERE uniqueIdentifier='{$uniqueIdentifier}'), '{$jobEventName}', '{$jobEventNotes}', '{$jobEventRate}', '{$tax1}', '{$quantity}');\n";
      }
      
    } /* END: If this is a file */
  } /* END: For each item in directory */
}
else
  echo "Error opening projects path.";

/* Send Projects to database */
echo 'SQL Query is ' . number_format(strlen($multi_query)/1000000,1) . " MB\n";
$db->multi_query($multi_query); while ($db->more_results()) {$db->next_result(); $db->store_result();} $multi_query = ''; // run and flush multi_query

echo "**** END: Importing Projects, Jobs, and Job Estimates **** \n\n";









/* Import Invoices */
echo "**** Importing Invoices **** \n";

/* Open invoices directory */
if($handle = opendir($pref_ibiz_dir . '/Invoices')){

  /* For each item in directory */
  while (false !== ($filename = readdir($handle))){
    
    /* If this is a file */
    if(substr($filename, 0, 1) != '.'){

      /* Load this invoice into array */
      $plist = new CFPropertyList($pref_ibiz_dir . '/Invoices/' . $filename . '/Attributes', CFPropertyList::FORMAT_XML);
      $invoice = $plist->toArray();
    
      /* Clean invoice strings */
      if(!empty($invoice['balance']))           $balance = $db->real_escape_string(trim($invoice['balance']));                   else $balance = NULL;
      if(!empty($invoice['invoiceAmount']))     $invoiceAmount = $db->real_escape_string(trim($invoice['invoiceAmount']));       else $invoiceAmount = NULL;
      if(!empty($invoice['clientIdentifier']))	$clientIdentifier = $db->real_escape_string(trim($invoice['clientIdentifier'])); else $clientIdentifier = NULL;      
      if(!empty($invoice['overdue']))	        $overdue = $db->real_escape_string(trim($invoice['overdue']));                   else $overdue = NULL;
      if(!empty($invoice['isEstimate']))	    $isEstimate = $db->real_escape_string(trim($invoice['isEstimate']));             else $isEstimate = NULL;
      if(!empty($invoice['date']))	            $date = $db->real_escape_string($invoice['date'] * 1000);                        else $date = NULL;
      if(!empty($invoice['dueDate']))	        $dueDate = $db->real_escape_string($invoice['dueDate'] * 1000);                  else $dueDate = NULL;
      if(!empty($invoice['uniqueIdentifier']))	$uniqueIdentifier = $db->real_escape_string($invoice['uniqueIdentifier']);       else $uniqueIdentifier = NULL;
      if(!empty($invoice['invoiceNumber']))	    $invoiceNumber = $db->real_escape_string($invoice['invoiceNumber']);             else $invoiceNumber = NULL;
      if(!empty($invoice['projectIDs'][0]))	    $projectID0 = $db->real_escape_string($invoice['projectIDs'][0]);                else $projectID0 = NULL;
      if(!empty($invoice['projectIDs'][1]))	    $projectID1 = $db->real_escape_string($invoice['projectIDs'][1]);                else $projectID1 = NULL;
      
      /* Construct mySQL query */
      if($invoiceNumber > 0)
	$multi_query .= "INSERT INTO Invoices (invoiceNumber, Customer, ProjectNum, ProjectNum2, Balance, Amount, Overdue, isEstimate, Date, DateDue, uniqueIdentifier) VALUES ('{$invoiceNumber}', (SELECT id FROM Customers WHERE addressBookId='{$clientIdentifier}'), (SELECT ProjectNum FROM Projects WHERE uniqueIdentifier='{$projectID0}'),(SELECT ProjectNum FROM Projects WHERE uniqueIdentifier='{$projectID1}'), '{$balance}', '{$invoiceAmount}', '{$overdue}' , '{$isEstimate}', '{$date}', '{$dueDate}',  '{$uniqueIdentifier}');";
      
    } /* END: If this is an invoice file */
  } /* END: For each item in this directory */
}

/* Send to database */
echo 'SQL Query is ' . number_format(strlen($multi_query)/1000000,1) . " MB\n";
$db->multi_query($multi_query); while ($db->more_results()) {$db->next_result(); $db->store_result();} $multi_query = ''; // run and flush multi_query
echo "**** END: Importing Invoices **** \n\n";









/* End script */
echo "\nScript Finished\n";
echo "</pre>";
?>