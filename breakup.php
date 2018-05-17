<?php

/**
 * Description: This script creates two JSON data files, one for a menu
 * and the second one with breakup data.  The JSON files are used as input
 * to an html page which plots the data via javascript.
 * Normally this script will be run daily to generate updated JSON files for
 * web display.
 *
 *
 *
 * @author Crane Johnson <benjamin.johnson@noaa.gov>
 * @version 0.1
 */

/**
 * Include Web Function Library
 */


require_once("/usr/local/apps/scripts/bcj/hydroTools/config.inc.php");


//Set the database
$mysqli->select_db("aprfc");



function formatDate($date){
	if($date){
		$date = date('M-d',strtotime($date));
	}
	else{
		$date = '';
	 }
	return $date;
}


$riverList = array();


//Get the number of data points for each siteID and store in $numcount array for use later
$numcount = array();
$query = "SELECT siteID, COUNT(*) FROM breakupdata where breakup is not null GROUP BY siteID";
$result = $mysqli->query($query) or die($mysqli->error);
while($row = $result->fetch_array()){
	$numcount[$row['siteID']] = $row['COUNT(*)'];
}


//Query the database and build the javascript menu array $rivers
//  "river  -  Location  (number of data points)
$query = "SELECT river,region,location,id,atnr FROM breakupSites ORDER BY region,river,location asc";
$result = $mysqli->query($query) or die($mysqli->error);
$option_tree = array();
$selectSet = array();
while($row = $result->fetch_array()){

    if(!isset($numcount[$row['id']])) {
        $numcount[$row['id']] = "0";
        continue;
    }
    #if($numcount[$row['id']]<0) continue;
    $selectSet[$row['id']] = "{$row['river']} {$row['atnr']} {$row['location']} (n={$numcount[$row['id']]})";

    if($row['location']){
        $option_tree[$row['region']][$row['river']][$row['location']."  (".$numcount[$row['id']]." yrs)"] = "{$row['region']} - {$row['river']} {$row['atnr']} {$row['location']} (ID:{$row['id']})";
    }
    else{
        $option_tree[$row['region']][$row['river']."  (".$numcount[$row['id']].")"] = " {$row['region']} - {$row['river']} {$row['atnr']} (ID:{$row['id']})";
    }

}


//Create the javascript menu $option_tree
foreach($option_tree as $region => $rivers){
	foreach($rivers as $river => $locations){
	    if(is_Array($option_tree[$region][$river])) {
	    	if(count($option_tree[$region][$river]) > 1){
	    		$option_tree[$region][$river] = array_merge(array('Table for all Locations' =>  "$river Table"),$option_tree[$region][$river]);
	    	}
	    	else{
	    	    $val = array_values($option_tree[$region][$river]);
	    		$option_tree[$region][$river] = array('Default'=>$val[0]);
	    	}
	    }
	}
}


//Save the menu data to a JSON file
file_put_contents('breakupMenuNew.json',json_encode($selectSet));
file_put_contents('breakupMenu.json',json_encode($option_tree));
chmod("breakupMenu.json", 0777);
chmod("breakupMenuNew.json", 0777);


//Start working on creating the breakup data JSON file
$query = "select dayofyear(breakup) as breakupDay,river,atnr,location,firstboat,unsafeman,unsafeveh,lastice,icemoved,remarks,breakup,siteID,year from breakupdata left join breakupSites on breakupdata.siteID = breakupSites.id order by river";
$result = $mysqli->query($query) or die($mysqli->error);
while($row = $result->fetch_array()){

    //Build the json structure from the query
    $siteData[$row['siteID']]['name'] = $row['river']." ".$row['atnr']." ".$row['location'];
    $siteData[$row['siteID']]['river'] = $row['river'];
    $siteData[$row['siteID']]['location'] = $row['location'];
    $siteData[$row['siteID']]['data'][$row['year']]['firstboat']= formatDate($row['firstboat']);
    $siteData[$row['siteID']]['data'][$row['year']]['unsafeman']= formatDate($row['unsafeman']);
    $siteData[$row['siteID']]['data'][$row['year']]['unsafeveh']= formatDate($row['unsafeveh']);
    $siteData[$row['siteID']]['data'][$row['year']]['lastice']  = formatDate($row['lastice']);
    $siteData[$row['siteID']]['data'][$row['year']]['icemoved']  = formatDate($row['icemoved']);
    $siteData[$row['siteID']]['data'][$row['year']]['remarks']=   $row['remarks'];

    $siteData[$row['siteID']]['data'][$row['year']]['jday'] =$row['breakupDay'];
    $siteData[$row['siteID']]['data'][$row['year']]['breakup'] = $row['breakup'];
    //Create a separate array that has paired (year,julianDay) for plotting
   if(isset($row['breakup'])){
        $siteData[$row['siteID']]['hdata'][]= array((int)$row['year'],(int)($row['breakupDay']));
    }
}

file_put_contents('breakupData.json',json_encode($siteData));
chmod("breakupData.json", 0777);





?>
