<?php

#require_once('/hd1apps/data/intranet/html/private/adminconnect.php');
#$host = 'localhost';
#$user = 'hydro';
#$pwd = 'fl00d!';
#$db = 'whitewinter_aprfc';



#$db = new mysqli($host,$user,$pwd,$db);

//Push the breakup data out to JSON files for public use
require_once('/hd1apps/data/intranet/html/tools/breakupDB/breakup.php');

require_once('/hd1apps/data/intranet/html/private/adminconnecti.php');
$mysqli->select_db("aprfc");

if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}



require_once('../tableEditor/tableEditori.php');



function validateDate($obj,$data)
{   
   
   if($data != ''){
	   if(!strtotime($data)) {
				$obj->addError('Invalid Breakup Date! Please enter a valid date');
		}
	   else{
		 $date = date('Y-m-d',strtotime($data));
	   }   
    }
    return $date;
}


$year = date('Y',time());

$editor = new TableEditor($mysqli,'breakupdata');
$editor->setSearchableFields('siteID','year');
$editor->setConfig('perPage', 500);
$editor->setConfig('allowEdit',true);
$editor->setConfig('title','APRFC River Breakup Database');
$editor->setConfig('allowAdd',true);
$editor->setConfig('allowCopy',false);
$editor->setConfig('allowDelete',true);
$editor->noEdit('datatable','lastUpdate');        	
$editor->setDefaultOrderby('id', 0);
$editor->setRequiredFields('siteID','year');
$editor->setDefaultValues(array(
	'year'		=> $year,
        'icemoved'	=> '',
	'breakup' 	=> '',
	'firstboat'	=> '',
	'unsafeman'	=> '',
  
	'unsafeveh'	=> '',
        'siteID'        => 'Site',
	'lastice'	=> ''));
$editor->noDisplay('river','atnr','location','region');
$editor->setInputType('siteID','select');
$editor->setValuesFromQuery('siteID','select id,concat(river," ",atnr," ",location) as longname  FROM breakupSites order by river,location');

$editor->addValidationCallback('breakup','validateDate');
$editor->setDisplayNames(array('forecastStart'       => 'Initial Forecast Start Date',
                              'forecastEnd'     => 'Initial Forecast End Date',
                              'severity' => 'Impact Based Flood Severity'));

$editor->addDisplayFilter('internalNotes', create_function('$v', 'return str_curtail($v, 20);'));

$editor->display();


?>
