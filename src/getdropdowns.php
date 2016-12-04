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

    $getVMobjectsSql = "
        select vmware_object_id, display_name
        from vmware_object
        where mor_type in ('ClusterComputeResource','HostSystem');
        ";

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
elseif ($query_type == "gethypervVMobjects")
{
    $db = new uptimeDB;
    $db->connectDB();

    $getVMobjectsSql = "select hyperv_object_id, display_name

     from hyperv_object
     where mor_type in ('HostSystem')  ";

    $results = $db->execQuery($getVMobjectsSql);
    foreach ($results as $row)
    {
        $id = $row['HYPERV_OBJECT_ID'];
        $name = $row['DISPLAY_NAME'];
        if (!preg_match("/deleted/", $name))
        {
            $json[$name] = $id;
        }
    }


	ksort($json);
    echo json_encode($json);
}
elseif ($query_type == "getAgentSystems")
{

    // Create API object
    $uptime_api = new uptimeApi($uptime_api_username, $uptime_api_password, $uptime_api_hostname, $uptime_api_port, $uptime_api_version, $uptime_api_ssl);
    $elements = $uptime_api->getElements("type=Server&isMonitored=1");
    foreach ($elements as $d) {
        if (!preg_match("/Vcenter/", $d['typeSubtype'] ) && !preg_match("/HyperVHost/", $d['typeSubtype']))
        {
            $has_ppg = False;
            foreach($d['monitors'] as $monitor)
            {
                if($monitor['name'] == "Platform Performance Gatherer")
                {
                    $has_ppg = True;
                    break;
                }
            }
            if ($has_ppg)
            {
                $k = $d['name'];
                $v = $d['id'];
                $json[$k] = $v;
            }
        }
    }
    //sort alphabeticaly on name instead of their order on ID
    ksort($json);  
    echo json_encode($json);
}

elseif ($query_type == "getVMdatastores")
{
    $db = new uptimeDB;
    $db->connectDB();

    $getVMobjectsSql = "
        SELECT
            vmware_object_id, display_name
        FROM
            vmware_object
        WHERE
            mor_type = 'Datastore'
        ";

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
elseif ($query_type == "gethypervVMdatastores")
{
    $db = new uptimeDB;
    $db->connectDB();

    $getVMobjectsSql = "select hyperv_object_id, display_name

     from hyperv_object
     where mor_type in ('Datastore' )  ";

    $results = $db->execQuery($getVMobjectsSql);
    foreach ($results as $row)
    {
        $id = $row['HYPERV_OBJECT_ID'];
        $name = $row['DISPLAY_NAME'];
        if (!preg_match("/deleted/", $name))
        {
            $json[$name] = $id;
        }
    }


    ksort($json);
    echo json_encode($json);
}
elseif ($query_type == "getXenServers") {
    $db = new uptimeDB;
    $db->connectDB();

    $get_xenserver_sql = "SELECT
                                e.display_name, e.entity_id
                            FROM
                                erdc_base b, erdc_configuration c, erdc_instance i, entity e
                            WHERE
                                b.name = 'XenServer' AND
                                b.erdc_base_id = c.erdc_base_id AND
                                c.id = i.configuration_id AND
                                i.entity_id = e.entity_id";

    $xenservers = $db->execQuery($get_xenserver_sql);
    foreach ($xenservers as $row) {
       $id = $row['ENTITY_ID'];
       $name = $row['DISPLAY_NAME'];
       $json[$name] = $id;

    }

    ksort($json);
    echo json_encode($json);
}

elseif ( $query_type == 'getXenServerDatastores')
{
    $db = new uptimeDB;
    $db->connectDB();
    
    $get_xenserver_datastores_sql = "
        SET NOCOUNT ON;
        SELECT
                e.display_name as NAME,
                e.entity_id as ID,
                ro.object_name as OBJ_NAME
        FROM
        erdc_base b
        JOIN erdc_configuration c
                ON (
                        b.name = 'XenServer' AND
                        b.erdc_base_id = c.erdc_base_id 
                        )
        JOIN erdc_instance i
                ON c.id = i.configuration_id
        JOIN entity e
                ON i.entity_id = e.entity_id
        JOIN ranged_object ro
                ON i.erdc_instance_id = ro.instance_id
        GROUP BY
        e.entity_id,
        e.display_name,
        ro.object_name
        ";
    
    $get_xenserver_datastores_mysql = "
        SELECT
            e.display_name as NAME, e.entity_id as ID, ro.object_name as OBJ_NAME
        FROM
            erdc_base b, erdc_configuration c, erdc_instance i, entity e, ranged_object ro
        WHERE
            b.name = 'XenServer' AND
            b.erdc_base_id = c.erdc_base_id AND
            c.id = i.configuration_id AND
            i.erdc_instance_id = ro.instance_id AND
            i.entity_id = e.entity_id
        GROUP BY
            e.entity_id,
            ro.object_name
        ";

	if ($db->dbType == 'mysql'){
		$datastore_results = $db->execQuery($get_xenserver_datastores_mysql);
	} else{
		$datastore_results = $db->execQuery($get_xenserver_datastores_sql);
	}

    foreach ($datastore_results as $row) {
        $id = $row['ID'] . "-" . $row['OBJ_NAME'];
        $name = $row['NAME'] . " - " . $row['OBJ_NAME'];
        $json[$name] = $id;
    }

    ksort($json);
    echo json_encode($json);
}


    
// Unsupported request
else {
    echo "Error: Unsupported Request '$query_type'" . "</br>";
    }

?>
