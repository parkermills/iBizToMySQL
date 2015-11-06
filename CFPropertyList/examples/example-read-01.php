<?php
/**
 * Examples for how to use CFPropertyList
 * Read an XML PropertyList
 * @package plist
 * @subpackage plist.examples
 */

// just in case...
error_reporting( E_ALL );
ini_set( 'display_errors', 'on' );

/**
 * Require CFPropertyList
 */
require_once(dirname(__FILE__).'/../CFPropertyList.php');


/*
 * create a new CFPropertyList instance which loads the sample.plist on construct.
 * since we know it's an XML file, we can skip format-determination
 */
$plist = new CFPropertyList( dirname(__FILE__).'/clients', CFPropertyList::FORMAT_XML );
$customerarray = $plist->toArray();
$customerarray = $customerarray['clients'];


/*
 * retrieve the array structure of sample.plist and dump to stdout
 */

foreach($customerarray as $customer){
	echo $customer['firstName'] . " " . $customer['lastName'];
	foreach($customer['projectIds'] as $project){
		$plist = new CFPropertyList( dirname(__FILE__).'/iBiz/', CFPropertyList::FORMAT_XML );
		echo "<br/>" . $project;
	}
	echo "<br/><br/>";
}

/*echo '<pre>';
var_dump($customerarray);
echo '</pre>';*/

?>