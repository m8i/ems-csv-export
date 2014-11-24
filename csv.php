<?php
/*
 * Create a CSV file of Events from the EMS API 
 * Usage: csv.php?bldg=[BUILDING ID NUMBER]
 * For all rooms, csv.php?bldg=0
 * 
 * Released under GPL License (http://www.gnu.org/copyleft/gpl.html)
 * Originally created by Matthew Irvine at the University of Mary Hardin-Baylor (http://www.umhb.edu)
 * 
 */
 
 /*
  * Set your variables here 
  */ 
 
	$ems_username="YOURUSERNAME";
	$ems_password="YOURPASSWORD";
	$ems_url="http://YOURSERVER/emsapi/service.asmx?wsdl";
	$allowed_event_status = array(1,7); // The ID of the event types you'd like included. Need to look in SQL table tblStatus in EMS database
	$php_date_format = "F j"; 			// How should dates be output in CSV? See docs at http://php.net/manual/en/function.date.php
	$php_time_format = "g:i a"; 		// How should times be output in CSV? See docs at http://php.net/manual/en/function.date.php
	$time_ahead=(2 * 24 * 60 * 60);		// How far in advance should we look for events. Default is 2 days (2 * 24 * 60 * 60)

 /* 
  * Variables are all set. No other edits are needed, but feel free to tweak as you desire.
  */ 
 
$bldgID=(int)$_GET['bldg'];

// Security measure... keep the bad guys out
if (!is_int($bldgID) || strlen($bldgID) > 5) { exit; }

if ($bldgID==0) { $bldgID=-1; }

$time = time();
$current_hour=date('G',$time);

// URI delivered to web service
$sc = new SOAPClient($ems_url, array(
      "trace"      => 1,		// enable trace to view what is happening
      "exceptions" => 0,		// disable exceptions
      "cache_wsdl" => 0) 		// disable any caching on the wsdl, encase you alter the wsdl server
);

// Make the request of the EMS API, and send the paramaters in the array
$result = $sc->GetAllBookings(array(
'UserName'=>$ems_username,
'Password'=>$ems_password,
'StartDate'=>date("Y-m-d\T00:00:00"),
'EndDate'=>date("Y-m-d\TH:i:s",time()+$time_ahead),
'BuildingID'=>$bldgID,
'ViewComboRoomComponents'=>TRUE ), NULL, NULL ); 
 
// Use Simple XML to decode the XML return from the EMS API
$xml=simplexml_load_string($result->GetAllBookingsResult);


$currentevent=0;
$i_echoed=0;
$outputarray=array();

//print_r($xml);

// Loop through the events to see what's coming up in this here room in the next few hours
foreach ($xml->Data as $this_event) {
	$eventname = str_replace('"', '', $this_event->EventName);
	$eventstart = strtotime($this_event->TimeEventStart);
	$eventend = strtotime($this_event->TimeEventEnd);
	$eventtype = $this_event->EventTypeID;
	$buildingname = str_replace('"', '', $this_event->Building);
	$room=$this_event->Room;
	$timeplus = time()+(24 * 60 * 60);

	// Check to make sure we're processing events of this type
	$none_shall_pass=0;
	foreach ($allowed_event_status as $allowed_status) {
		if ($this_event->StatusID == $allowed_status) { $none_shall_pass=1; }
	}
	if($none_shall_pass == 0) {continue;}

	
	if(!$eventstart) {continue;}
	
	if (($eventstart<=$time && $time<=$eventend ) || ($eventend>=$time && $eventstart<=$timeplus)) {
		$outputarray[]=$eventstart.',"'.date($php_date_format,$eventstart).'","'.date($php_time_format,$eventstart).'","'.date($php_date_format,$eventend).'","'.date($php_time_format,$eventend).'","'.$eventname.'","'.$buildingname.'","'.$room.'","'.$eventtype.'"';
	}
} 

sort($outputarray);

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=ems_events_".$bldgID.".csv");
header("Pragma: no-cache");
header("Expires: 0");
echo '"Sort","Event Start Date","Event Start Time","Event End Date","Event End Time","Event Name","Building Name","Room","Event Type"'."\n";
foreach ($outputarray as $line) {
	echo $line."\n";
}

?>

