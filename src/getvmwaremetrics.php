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
header("Content-type: text/json");
include("uptimeDB.php");

if (isset($_GET['query_type'])){
	$query_type = $_GET['query_type'];
}
if (isset($_GET['uptime_offset'])){
	$offset = $_GET['uptime_offset'];
}
if (isset($_GET['time_frame'])){
	$time_frame = $_GET['time_frame'];
} else {
	$time_frame = 3;
}
if (isset($_GET['metricType'])){
	$metricType = $_GET['metricType'];
}
if (isset($_GET['element'])){
	$vmware_object_id = $_GET['element'];
}
$json = array();
$oneElement = array();
$performanceData = array();
//date_default_timezone_set('UTC');

$db = new uptimeDB;
if ($db->connectDB()) {
	echo "";
} else {
	echo "unable to connect to DB exiting";	
	exit(1);
}

if ($query_type == "vmware-Mem")
{
	$min_mem_usage_array = array();
	$max_mem_usage_array = array();
	$avg_mem_usage_array = array();
	$hostMemResults = array();

    $sql = "
        SET nocount ON;
        DECLARE @vmware_object_id int;
        DECLARE @time_frame int;
        DECLARE @time_from date;

        SET @vmware_object_id = $vmware_object_id;
        SET @time_frame = $time_frame;
        SET @time_from = DATEADD(month, -@time_frame, GETDATE())

        SELECT
            s.vmware_object_id,
            o.vmware_name as NAME,
            MIN(cast(s.sample_time as date)) as SAMPLE_TIME,
            min(a.memory_usage) as MIN_MEM_USAGE,
            max(a.memory_usage) as MAX_MEM_USAGE,
            avg(a.memory_usage) as AVG_MEM_USAGE,
            min(a.memory_total) as TOTAL_CAPACITY
		FROM
            vmware_perf_aggregate a
		JOIN vmware_perf_sample s
            ON (
                s.sample_id = a.sample_id AND
                s.sample_time > @time_from
            )
		JOIN vmware_object o
            ON (
                s.vmware_object_id = o.vmware_object_id AND
                s.vmware_object_id = @vmware_object_id
            )
        GROUP BY
            s.vmware_object_id,
            o.vmware_name,
            year(s.sample_time),
            month(s.sample_time),
            day(s.sample_time)
		ORDER BY
			year(s.sample_time),
            month(s.sample_time),
			day(s.sample_time)";
			
	$oraclesql = "
	SELECT
		s.vmware_object_id,
		o.vmware_name as NAME,
		MIN(cast(s.sample_time as date)) as SAMPLE_TIME,
		min(a.memory_usage) as MIN_MEM_USAGE,
		max(a.memory_usage) as MAX_MEM_USAGE,
		avg(a.memory_usage) as AVG_MEM_USAGE,
		min(a.memory_total) as TOTAL_CAPACITY
	FROM
		vmware_perf_aggregate a
	JOIN vmware_perf_sample s
		ON (
			s.sample_id = a.sample_id AND
			s.sample_time > ADD_MONTHS(SYSDATE,-$time_frame)
		)
	JOIN vmware_object o
		ON (
			s.vmware_object_id = o.vmware_object_id AND
			s.vmware_object_id = $vmware_object_id
		)
	GROUP BY
		s.vmware_object_id,
		o.vmware_name,
		EXTRACT(YEAR FROM s.sample_time),
		EXTRACT(MONTH FROM s.sample_time), 
		EXTRACT(DAY FROM s.sample_time)
	ORDER BY
		EXTRACT(YEAR FROM s.sample_time),
		EXTRACT(MONTH FROM s.sample_time), 
		EXTRACT(DAY FROM s.sample_time)";

    $mysql = "SELECT
		s.vmware_object_id,
		o.vmware_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		min(a.memory_usage) as MIN_MEM_USAGE,
		max(a.memory_usage) as MAX_MEM_USAGE,
		avg(a.memory_usage) as AVG_MEM_USAGE,
		a.memory_total		as TOTAL_CAPACITY,
		day(s.sample_time),
		month(s.sample_time),
		year(s.sample_time)
	FROM
		vmware_perf_aggregate a, vmware_perf_sample s, vmware_object o
	WHERE
		s.sample_id = a.sample_id AND
		s.vmware_object_id = o.vmware_object_id AND
		s.sample_time > date_sub(now(),interval  " . $time_frame . " month) AND
		s.vmware_object_id = " .$vmware_object_id . "
	GROUP BY
		s.vmware_object_id,
		year(s.sample_time),
		month(s.sample_time),
		day(s.sample_time)";

	if ($db->dbType == 'mysql'){
		$hostMemResults = $db->execQuery($mysql);
	} else if ($db->dbType == 'mssql'){
		$hostMemResults = $db->execQuery($sql);
	} else {
		$hostMemResults = $db->execQuery($oraclesql);
	}
	if(isset($hostMemResults[0])) {
		$name = $hostMemResults[0]['NAME'];
	} else {
		exit("host memory array is empty");
	}
	$memScale = 1e-6;

	foreach ($hostMemResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME'])-$offset;
		$x = $sample_time * 1000;

		$data = array($x, floatval($row['MIN_MEM_USAGE'] * $memScale));
		array_push($min_mem_usage_array, $data);

		$data = array($x, floatval($row['MAX_MEM_USAGE'] * $memScale));
		array_push($max_mem_usage_array, $data);

		$data = array($x, floatval($row['AVG_MEM_USAGE'] * $memScale));
		array_push($avg_mem_usage_array, $data);
	}

	$capacity = floatval($hostMemResults[0]['TOTAL_CAPACITY'] * $memScale);

	if ($metricType == 'min') {
		$my_series = array(
			'name' => $name . " - Daily Mem Min",
			'capacity' => $capacity,
			'unit' => 'GB',
			'series' => $min_mem_usage_array
		);
	}

	if ($metricType == 'max') {
		$my_series = array(
			'name' => $name . " - Daily Mem Max",
			'capacity' => $capacity,
			'unit' => 'GB',
			'series' => $max_mem_usage_array
		);
	}

	if ($metricType == 'avg') {
		$my_series = array(
			'name' => $name . " - Daily Mem Avg",
			'capacity' => $capacity,
			'unit' => 'GB',
			'series' => $avg_mem_usage_array
		);
	}
	
	if (count($my_series['series']) > 0) {
		array_push($json, $my_series);
	}
	if (count($json) > 0) {
		echo json_encode($json);
	} else {
		echo "No Data";
	}
}

elseif ($query_type == "vmware-Cpu") {
	$min_cpu_usage_array = array();
	$max_cpu_usage_array = array();
	$avg_cpu_usage_array = array();
	$hostCpuResults = array();
	
    $sql = "
        SET nocount ON;
        DECLARE @vmware_object_id int;
        DECLARE @time_frame int;
        DECLARE @time_from date;

        SET @vmware_object_id = $vmware_object_id;
        SET @time_frame = $time_frame;
        SET @time_from = DATEADD(month, -@time_frame, GETDATE())

        SELECT
            s.vmware_object_id,
            o.vmware_name as NAME,
            min(cast(s.sample_time as date)) as SAMPLE_TIME,
            min(a.cpu_usage) as MIN_CPU_USAGE,
            max(a.cpu_usage) as MAX_CPU_USAGE,
            avg(a.cpu_usage) as AVG_CPU_USAGE,
            min(a.cpu_total) as TOTAL_CAPACITY
		FROM
            vmware_perf_aggregate a
		JOIN vmware_perf_sample s
            ON (
				s.sample_id = a.sample_id AND
				s.sample_time > @time_from
            )
		JOIN vmware_object o
            ON (
				s.vmware_object_id = o.vmware_object_id AND
				s.vmware_object_id = @vmware_object_id
            )
        GROUP BY
            s.vmware_object_id,
            o.vmware_name,
            year(s.sample_time),
            month(s.sample_time),
			day(s.sample_time)";
	
	$oraclesql="
        SELECT
            s.vmware_object_id,
            o.vmware_name as NAME,
            CAST(s.sample_time AS DATE) as SAMPLE_TIME,
            min(a.cpu_usage) as MIN_CPU_USAGE,
            max(a.cpu_usage) as MAX_CPU_USAGE,
            avg(a.cpu_usage) as AVG_CPU_USAGE,
			min(a.cpu_total) as TOTAL_CAPACITY
		FROM
            vmware_perf_aggregate a
		JOIN vmware_perf_sample s
            ON (
				s.sample_id = a.sample_id AND
				s.sample_time > ADD_MONTHS(SYSDATE,-$time_frame)
            )
		JOIN vmware_object o
            ON (
				s.vmware_object_id = o.vmware_object_id AND
				s.vmware_object_id = $vmware_object_id
            )
        GROUP BY
            s.vmware_object_id,
			o.vmware_name,
			s.sample_time,
            EXTRACT(YEAR FROM s.sample_time),
			EXTRACT(MONTH FROM s.sample_time),
			EXTRACT(DAY FROM s.sample_time)";
        
    $mysql = "SELECT
		s.vmware_object_id,
		o.vmware_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		min(a.cpu_usage) as MIN_CPU_USAGE,
		max(a.cpu_usage) as MAX_CPU_USAGE,
		avg(a.cpu_usage) as AVG_CPU_USAGE,
		a.cpu_total as TOTAL_CAPACITY,
		day(s.sample_time),
		month(s.sample_time),
		year(s.sample_time)
	FROM
		vmware_perf_aggregate a, vmware_perf_sample s, vmware_object o
	WHERE
		s.sample_id = a.sample_id AND
		s.vmware_object_id = o.vmware_object_id AND
		s.sample_time > date_sub(now(),interval  " . $time_frame . " month) AND
		s.vmware_object_id = $vmware_object_id

	GROUP BY
		s.vmware_object_id,
		year(s.sample_time),
		month(s.sample_time),
		day(s.sample_time)
	ORDER BY
		year(s.sample_time),
		month(s.sample_time),
		day(s.sample_time)";

	if ($db->dbType == 'mysql'){
		$hostCpuResults = $db->execQuery($mysql);
	} else if ($db->dbType == 'mssql'){
		$hostCpuResults = $db->execQuery($mssql);
	} else{
		$hostCpuResults = $db->execQuery($oraclesql);
	}

	if(isset($hostCpuResults[0])) {
		$name = $hostCpuResults[0]['NAME'];
	} else {
		exit("host cpu array is empty");
	}
	$cpuScale = 1000;

	foreach ($hostCpuResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME'])-$offset;
		$x = $sample_time * 1000;

		$data = array($x, floatval($row['MIN_CPU_USAGE'] / $cpuScale));
		array_push($min_cpu_usage_array, $data);

		$data = array($x, floatval($row['MAX_CPU_USAGE'] / $cpuScale));
		array_push($max_cpu_usage_array, $data);

		$data = array($x, floatval($row['AVG_CPU_USAGE'] / $cpuScale));
		array_push($avg_cpu_usage_array, $data);
	}

	$capacity = floatval($hostCpuResults[0]['TOTAL_CAPACITY'] / $cpuScale);

	if ($metricType == 'min') {
		$my_series = array(
			'name' => $name . " - Daily Cpu Min",
			'capacity' => $capacity,
			'unit' => 'GHz',
			'series' => $min_cpu_usage_array
		);
	}

	if ($metricType == 'max') {
		$my_series = array(
			'name' => $name . " - Daily Cpu Max",
			'capacity' => $capacity,
			'unit' => 'GHz',
			'series' => $max_cpu_usage_array
		);
	}

	if ($metricType == 'avg') {
		$my_series = array(
			'name' => $name . " - Daily Cpu Avg",
			'capacity' => $capacity,
			'unit' => 'GHz',
			'series' => $avg_cpu_usage_array
		);
	}

	if (count($my_series['series']) > 0) {
		array_push($json, $my_series);
	}
	if (count($json) > 0) {
		echo json_encode($json);
	} else {
		echo "No Data";
	}
}

elseif ( $query_type == "vmware-Datastore")
{
	$min_datastore_usage_array = array();
	$max_datastore_usage_array = array();
	$avg_datastore_usage_array = array();
	$min_datastore_prov_array = array();
	$max_datastore_prov_array = array();
	$avg_datastore_prov_array = array();
   	$datastoreResults = array();
	
	$datastoreSql = "
        SET nocount ON;
        DECLARE @vmware_object_id int;
        DECLARE @time_frame int;
        DECLARE @time_from date;

        SET @vmware_object_id = $vmware_object_id;
        SET @time_frame = $time_frame;
        SET @time_from = DATEADD(month, -@time_frame, GETDATE())

        SELECT
            s.vmware_object_id,
            o.vmware_name as NAME,
            min(cast(s.sample_time as date)) as SAMPLE_TIME,
            min(u.usage_total) as MIN_USAGE,
            max(u.usage_total) as MAX_USAGE,
            avg(u.usage_total) as AVG_USAGE,
            min(u.provisioned) as MIN_PROV,
            max(u.provisioned) as MAX_PROV,
            avg(u.provisioned) as AVG_PROV,
			(SELECT capacity FROM vmware_perf_datastore_usage vpdu
				INNER JOIN vmware_latest_datastore_sample vlds 
				ON vlds.sample_id = vpdu.sample_id and vlds.vmware_object_id = @vmware_object_id) AS CURR_CAPACITY,
            min(u.capacity) as TOTAL_CAPACITY
        FROM
            vmware_perf_datastore_usage u
		JOIN vmware_perf_sample s
            ON (
                s.sample_id = u.sample_id AND
                s.sample_time > @time_from
            )
		JOIN vmware_object o
            ON (
                s.vmware_object_id = o.vmware_object_id AND
                s.vmware_object_id = @vmware_object_id
            )
        GROUP BY
            s.vmware_object_id,
            o.vmware_name,
            year(s.sample_time),
            month(s.sample_time),
            day(s.sample_time)
		ORDER BY
			year(s.sample_time),
            month(s.sample_time),
			day(s.sample_time)";		
			
			$datastoreOracleSql = "
			SELECT
				s.vmware_object_id,
				o.vmware_name as NAME,
				CAST(s.sample_time AS DATE) as SAMPLE_TIME,
				min(u.usage_total) as MIN_USAGE,
				max(u.usage_total) as MAX_USAGE,
				avg(u.usage_total) as AVG_USAGE,
				min(u.provisioned) as MIN_PROV,
				max(u.provisioned) as MAX_PROV,
				avg(u.provisioned) as AVG_PROV,
				(SELECT capacity FROM vmware_perf_datastore_usage vpdu
					INNER JOIN vmware_latest_datastore_sample vlds 
					ON vlds.sample_id = vpdu.sample_id and vlds.vmware_object_id = $vmware_object_id) AS CURR_CAPACITY,
				min(u.capacity) as TOTAL_CAPACITY
			FROM
				vmware_perf_datastore_usage u
			JOIN vmware_perf_sample s
				ON (
					s.sample_id = u.sample_id AND
					s.sample_time > ADD_MONTHS(SYSDATE,-$time_frame)
				)
			JOIN vmware_object o
				ON (
					s.vmware_object_id = o.vmware_object_id AND
					s.vmware_object_id = $vmware_object_id
				)
			GROUP BY
				s.vmware_object_id,
				o.vmware_name,
				s.sample_time,
				EXTRACT(YEAR FROM s.sample_time),
				EXTRACT(MONTH FROM s.sample_time), 
				EXTRACT(DAY FROM s.sample_time)
			ORDER BY
				EXTRACT(YEAR FROM s.sample_time),
				EXTRACT(MONTH FROM s.sample_time), 
				EXTRACT(DAY FROM s.sample_time)";	
	
	$datastoremySql = "
	SELECT 
		s.vmware_object_id, 
		o.vmware_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		min(u.usage_total) as MIN_USAGE,
		max(u.usage_total) as MAX_USAGE,
		avg(u.usage_total) as AVG_USAGE,
		min(u.provisioned) as MIN_PROV,
		max(u.provisioned) as MAX_PROV,
		avg(u.provisioned) as AVG_PROV,
		(SELECT capacity FROM vmware_perf_datastore_usage vpdu
		INNER JOIN uptime.vmware_latest_datastore_sample vlds 
		ON vlds.sample_id = vpdu.sample_id and vlds.vmware_object_id = $vmware_object_id) AS CURR_CAPACITY,
		u.capacity as TOTAL_CAPACITY,
		day(s.sample_time), 
		month(s.sample_time), 
		year(s.sample_time) 
	FROM 
		vmware_perf_datastore_usage u, vmware_perf_sample s, vmware_object o
	WHERE 
		s.sample_id = u.sample_id AND 
		s.vmware_object_id = o.vmware_object_id AND
		s.sample_time > date_sub(now(),interval  ". $time_frame . " month) AND
		s.vmware_object_id = $vmware_object_id
	GROUP BY 
		s.vmware_object_id,
		year(s.sample_time),
		month(s.sample_time), 
		day(s.sample_time)
	ORDER BY
		year(s.sample_time),
		month(s.sample_time),
		day(s.sample_time)";

	if ($db->dbType == 'mysql'){
		$datastoreResults = $db->execQuery($datastoremySql);
	} elseif ($db->dbType == 'mssql'){
		$datastoreResults = $db->execQuery($datastoreMsSql);
	} else {
		$datastoreResults = $db->execQuery($datastoreOracleSql);
	}

	if (isset($datastoreResults[0])) {
		$name = $datastoreResults[0]['NAME'];
		} else {
			exit("datastore array is empty ($datastoreResults)");
		}
	$capacity = floatval($datastoreResults[0]['CURR_CAPACITY']);
	$datastoreScale = 1e-6;
	
	foreach ($datastoreResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME'])-$offset;
		$x = $sample_time * 1000;

		$data = array($x, floatval($row['MIN_USAGE'] * $datastoreScale));
		array_push($min_datastore_usage_array, $data);

		$data = array($x, floatval($row['MAX_USAGE'] * $datastoreScale));
		array_push($max_datastore_usage_array, $data);

		$data = array($x, floatval($row['AVG_USAGE'] * $datastoreScale));
		array_push($avg_datastore_usage_array, $data);

		$data = array($x, floatval($row['MIN_PROV'] * $datastoreScale));
		array_push($min_datastore_prov_array, $data);

		$data = array($x, floatval($row['MAX_PROV'] * $datastoreScale));
		array_push($max_datastore_prov_array, $data);

		$data = array($x, floatval($row['AVG_PROV'] * $datastoreScale));
		array_push($avg_datastore_prov_array, $data);
	}

	if ($metricType == 'min')
	{
		$usage_series = array(
			'name' => $name . " - Daily Actual Min",
			'capacity' => $capacity,
			'unit' => 'GBs',
			'series' => $min_datastore_usage_array
			);
		$prov_series = array(
			'name' => $name . " - Daily Provisioned Min",
			'capacity' => $capacity,
			'unit' => 'GBs',
			'series' => $min_datastore_prov_array
			);
	}

	if ($metricType == 'max')
	{
		$usage_series = array(
			'name' => $name . " - Daily Actual Max",
			'capacity' => $capacity,
			'unit' => 'GBs',
			'series' => $max_datastore_usage_array
			);
		$prov_series = array(
			'name' => $name . " - Daily Provisioned Max",
			'capacity' => $capacity,
			'unit' => 'GBs',
			'series' => $max_datastore_prov_array
			);
	}

	if ($metricType == 'avg')
	{
		$usage_series = array(
			'name' => $name . " - Daily Actual Avg",
			'capacity' => $capacity,
			'unit' => 'GBs',
			'series' => $avg_datastore_usage_array
			);
		$prov_series = array(
			'name' => $name . " - Daily Provisioned Avg",
			'capacity' => $capacity,
			'unit' => 'GBs',
			'series' => $avg_datastore_prov_array
			);
	}

	if (count($usage_series['series']) > 0)
	{
		array_push($json, $usage_series);
	}
	if (count($json) > 0)
	{
		echo json_encode($json);
	}
	else
	{
		echo "No Data";
	}
}

// Unsupported request
else { echo "Error: Unsupported Request '$query_type'" . "</br>";}

// close sessions
$db->closeDB();
?>
