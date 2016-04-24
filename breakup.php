<?php

/**
 * Description: This script creates two JSON data files, one for a menu
 * and the second one with breakup data.  The JSON files are used as input
 * to an html page which plots the data via javascript.
 * Normally this script will be run daily to generate updated JSON files for
 * web display.  
 *
 * It can also be run from a browser to output a table of average breakup dates
 * breakup.php?t&s=1980&e=2016
 *
 
 * @author Crane Johnson <benjamin.johnson@noaa.gov>
 * @version 0.1
 */

/**
 * Include Web Function Library
 */


require_once("/usr/local/apps/scripts/bcj/hydroTools/config.inc.php");
require_once(RESOURCES_DIRECTORY."web_functions.php");

//Set the database
$mysqli->select_db("aprfc");

//Optional arguments for calculating average breakup dates over a range of years
$opts = getoptreq('s:e:t', array());

if(isset($opts["s"])){
    $start =  $opts['s'];
}
else{
    $start =  1980;
}

if(isset($opts["s"])){
    $end = $opts['e'];
}
else{
    $end = date('Y',time());
}

if(isset($opts["t"])){
    $table = true;
}
else{
    $table = false;
}

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
while($row = $result->fetch_array()){

    if(!isset($numcount[$row['id']])) {
        $numcount[$row['id']] = "0";
        continue;
    }
    #if($numcount[$row['id']]<0) continue;
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
file_put_contents('breakupMenu.json',json_encode($option_tree));
file_put_contents('cms_publicdata+breakupMenu.json',json_encode($option_tree));
chmod("breakupMenu.json", 0777);
chmod("cms_publicdata+breakupMenu.json", 0777);



//Start working on created the breakup data JSON file
$query = "select dayofyear(breakup) as breakupDay,river,atnr,location,firstboat,unsafeman,unsafeveh,lastice,icemoved,remarks,breakup,siteID,year from breakupdata left join breakupSites on breakupdata.siteID = breakupSites.id order by siteID,year";
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
file_put_contents('cms_publicdata+breakupData.json',json_encode($siteData));
chmod("breakupData.json", 0777);
chmod("cms_publicdata+breakupData.json", 0777);


//If an average dates table was requested display this back to the browser.
if ($table) get_all_avg_dates(1980,$end,$mysqli);

function get_all_avg_dates($from,$to,$mysqli){
    echo"<h2>Temp Table 1980-2015</h2>";
    echo "<table border = '1'><tr><td>Name</td><td>Avg Breakup Date</td><td>Count</td><td>".date('Y')."</td></tr>";
    $query = "SELECT id,region,river,atnr,location FROM breakupSites order by region,river";

    $result = $mysqli->query($query); # or die($mysqli->error);
    echo $mysqli->error;

    while($row = $result->fetch_array()){
        $name = $row['river'].' '.$row['atnr'].' '.$row['location'];
        $query = "select siteid,avg(DATE_FORMAT(breakup,'%j'))+.5 AS jday,count(breakup) as count from breakupdata where siteid = ".$row['id']." and  year >= $from and year <= $to";
        $resultd = $mysqli->query($query) or die($mysqli->error);
        $datarow = $resultd->fetch_assoc();
        if($datarow['jday'] > 0){
            $date = date('M-d',strtotime('2001-12-31')+$datarow['jday']*24*3600);
            $query = "select siteid,DATE_FORMAT(breakup,'%j') as jday from breakupdata where siteid = ".$row['id']." and  year = YEAR(CURDATE())";
            $resultd = $mysqli->query($query) or die($mysqli->error);
            $data = $resultd->fetch_assoc();
            if($data['jday']){
                $currYear = date('M-d',strtotime('2001-12-31')+$data['jday']*24*3600);
            }
            else{
                $currYear = '-';
            }
            echo "<tr><td>$name</td><td>$date</td><td>{$datarow['count']}</td><td>$currYear</td></tr>";

            }
        else{
            $date = 'N/A';
        }



    }
    echo "</table>";

}

?>
