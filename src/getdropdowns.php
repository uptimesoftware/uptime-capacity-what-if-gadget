<?php 

//DISCLAIMER:
//LIMITATION OF LIABILITY: uptime software does not warrant that software obtained
//from the Grid will meet your requirements or that operation of the software will
//be uninterrupted or error free. By downloading and installing software obtained
//from the Grid you assume all responsibility for selecting the appropriate
//software to achieve your intended results and for the results obtained from use
//of software downloaded from the Grid. uptime software will not be liable to you
//or any party related to you for any loss or damages resulting from any claims,
//demands or actions arising out of use of software obtained from the Grid. In no
//event shall uptime software be liable to you or any party related to you for any
//indirect, incidental, consequential, special, exemplary or punitive damages or
//lost profits even if uptime software has been advised of the possibility of such
//damages.

// Set the JSON header

require_once $_SERVER['DOCUMENT_ROOT'] . "/includes/classLoader.inc";


session_start();

$user_name =  $_SESSION['current_user']->getName();
$user_pass = $_SESSION['current_user']->getPassword();

session_write_close();



header("Content-type: text/json");

include("uptimeDB.php");
include("uptimeApi.php");

if (isset($_GET['query_type'])){
	$query_type = $_GET['query_type'];
}
if (isset($_GET['uptime_offest'])){
	$offset = $_GET['uptime_offest'];
}
if (isset($_GET['time_frame'])){
	$time_frame = $_GET['time_frame'];
}
$json = array();
$oneElement = array();
$performanceData = array();
//date_default_timezone_set('UTC');

$uptime_api_username = $user_name;
$uptime_api_password = $user_pass;
$uptime_api_hostname = "localhost";     // up.time Controller hostname (usually localhost, but not always)
$uptime_api_port = 9997;
$uptime_api_version = "v1";
$uptime_api_ssl = true;


if ($query_type == "getEsxHosts")
{
	//get the list of ESX Hosts the user is able to see according to the API.
	$uptime_api = new uptimeApi($uptime_api_username, $uptime_api_password, $uptime_api_hostname, $uptime_api_port, $uptime_api_version, $uptime_api_ssl);

    $elements = $uptime_api->getElements("type=Server&isMonitored=1");

    foreach ($elements as $key => $value) {

    	if( preg_match("/ESX/", $value['typeOs']) )
    	{
    		$id = $value['id'];
    		$name = $value['name'];

    		$json[$name] = $id;
    	}
    }

    ksort($json);
    echo json_encode($json);
	

}

elseif ($query_type == "getVMobjects")
{
    $db = new uptimeDB;
    $db->connectDB();

    $getVMobjectsSql = 'select vmware_object_id, display_name

     from vmware_object
     where mor_type in ("ClusterComputeResource","HostSystem" )  ';

    $results = $db->execQuery($getVMobjectsSql);
    foreach ($results as $row)
    {
        $id = $row['VMWARE_OBJECT_ID'];
        $name = $row['DISPLAY_NAME'];
        if (!preg_match("/deleted/", $name))
        {
            $json[$name] = $id;
        }
    }


	ksort($json);
    echo json_encode($json);
}


    
// Unsupported request
else {
    echo "Error: Unsupported Request '$query_type'" . "</br>";
    }

?>
