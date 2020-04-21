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
}
else
{
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
if ($db->connectDB())
{
	echo "";

}
else
{
 echo "unable to connect to DB exiting";
 exit(1);
}


if ( $query_type == "osperf-Mem")
{

	$min_mem_usage_array = array();
	$max_mem_usage_array = array();
	$avg_mem_usage_array = array();
	$hostMemResults = array();

	$mssql ="
	SELECT
		e.entity_id,
		e.display_name as NAME,
		CONVERT(date, s.sample_time) as SAMPLE_TIME,
		min(a.free_mem) as MIN_MEM_USAGE,
		max(a.free_mem) as MAX_MEM_USAGE,
		avg(a.free_mem) as AVG_MEM_USAGE,
		min(c.memsize) as TOTAL_CAPACITY,
		max(c.memsize),
		avg(c.memsize),
		day(s.sample_time),
		month(s.sample_time),
		year(s.sample_time)
	FROM
		performance_aggregate a, performance_sample s, entity e, entity_configuration c
	WHERE
		s.id = a.sample_id AND
		s.uptimehost_id = e.entity_id AND
		e.entity_id = c.entity_id AND
		e.entity_id = $vmware_object_id AND
		s.sample_time > dateadd(month,-$time_frame,getdate())
		
	GROUP BY
		e.entity_id,
		e.display_name,
		s.sample_time,
		year(s.sample_time),
		month(s.sample_time),
		day(s.sample_time)";

	$oraclesql="SELECT
	e.entity_id,
	e.display_name as NAME,
	CAST(s.sample_time AS DATE) as SAMPLE_TIME,
	min(a.free_mem) as MIN_MEM_USAGE,
	max(a.free_mem) as MAX_MEM_USAGE,
	avg(a.free_mem) as AVG_MEM_USAGE,
	min(c.memsize) as TOTAL_CAPACITY,
	max(c.memsize),
	avg(c.memsize),
	EXTRACT(YEAR FROM s.sample_time),
	EXTRACT(MONTH FROM s.sample_time), 
	EXTRACT(DAY FROM s.sample_time)
FROM
	performance_aggregate a, performance_sample s, entity e, entity_configuration c
WHERE
	s.id = a.sample_id AND
	s.uptimehost_id = e.entity_id AND
	e.entity_id = c.entity_id AND
	e.entity_id = $vmware_object_id AND
	s.sample_time > ADD_MONTHS(SYSDATE,-$time_frame)
GROUP BY
	e.entity_id,
	e.display_name,
	s.sample_time,
	EXTRACT(YEAR FROM s.sample_time),
	EXTRACT(MONTH FROM s.sample_time), 
	EXTRACT(DAY FROM s.sample_time)";

	$mysql="
	SELECT
				e.entity_id,
				e.display_name as NAME,
				date(s.sample_time) as SAMPLE_TIME,
				min(a.free_mem) as MIN_MEM_USAGE,
				max(a.free_mem) as MAX_MEM_USAGE,
				avg(a.free_mem) as AVG_MEM_USAGE,
				min(c.memsize) as TOTAL_CAPACITY,
				max(c.memsize),
				avg(c.memsize),
				day(s.sample_time),
				month(s.sample_time),
				year(s.sample_time)
			FROM
				performance_aggregate a, performance_sample s, entity e, entity_configuration c
			WHERE
				s.id = a.sample_id AND
				s.uptimehost_id = e.entity_id AND
				e.entity_id = c.entity_id AND
				s.sample_time > date_sub(now(),interval  ". $time_frame . " month) AND
				e.entity_id = $vmware_object_id
			GROUP BY
				e.entity_id,
				e.display_name,
				s.sample_time,
				year(s.sample_time),
				month(s.sample_time),
			day(s.sample_time)";
	
	if ($db->dbType == 'mysql'){
		$hostMemResults = $db->execQuery($mysql);
	} else if ($db->dbType == 'mssql'){
		$hostMemResults = $db->execQuery($mssql);
	} else {
		$hostMemResults = $db->execQuery($oraclesql);
	}
		
	if(isset($hostMemResults[0])) {
		$name = $hostMemResults[0]['NAME'];
	} else {
		exit("host mem array is empty");
	}


	/*$name = $hostMemResults[0]['NAME'];*/
	$memScale = 1e-6;

	foreach ((array)$hostMemResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME'])-$offset;
		$x = $sample_time * 1000;

		$data = array($x, floatval($row['MIN_MEM_USAGE'] * $memScale ));
		array_push($min_mem_usage_array, $data);

		$data = array($x, floatval($row['MAX_MEM_USAGE'] * $memScale ));
		array_push($max_mem_usage_array, $data);

		$data = array($x, floatval($row['AVG_MEM_USAGE'] * $memScale ));
		array_push($avg_mem_usage_array, $data);
	}

	$capacity = floatval($hostMemResults[0]['TOTAL_CAPACITY'] * $memScale);

	if ($metricType == 'min')
	{
		$my_series = array(
			'name' => $name . " - Daily Mem Min",
			'capacity' => $capacity,
			'unit' => 'GB',
			'series' => $min_mem_usage_array

			);
	}

	if ($metricType == 'max')
	{
		$my_series = array(
			'name' => $name . " - Daily Mem Max",
			'capacity' => $capacity,
			'unit' => 'GB',
			'series' => $max_mem_usage_array

			);
	}

	if ($metricType == 'avg')
	{
		$my_series = array(
			'name' => $name . " - Daily Mem Avg",
			'capacity' => $capacity,
			'unit' => 'GB',
			'series' => $avg_mem_usage_array
			);
	}


	if (count((array)$my_series['series']) > 0)
	{
		array_push($json, $my_series);
	}
	if (count((array)$json) > 0)
	{
		echo json_encode($json);
	}
	else
	{
		echo "No Data";
	}
}

elseif ( $query_type == "osperf-Cpu")
{

	$min_cpu_usage_array = array();
	$max_cpu_usage_array = array();
	$avg_cpu_usage_array = array();
	$hostCpuResults = array();

	$oraclesql="
	SELECT
		e.entity_id,
		e.display_name as NAME,
		CAST(s.sample_time AS DATE) as SAMPLE_TIME,
		min(a.cpu_usr + a.cpu_sys + a.cpu_wio) as MIN_CPU_USAGE,
		max(a.cpu_usr + a.cpu_sys + a.cpu_wio) as MAX_CPU_USAGE,
		avg(a.cpu_usr + a.cpu_sys + a.cpu_wio) as AVG_CPU_USAGE,
		c.numcpus as NUM_CPU,
		u.mhz as TOTAL_MHZ,
		EXTRACT(YEAR FROM s.sample_time),
		EXTRACT(MONTH FROM s.sample_time),
		EXTRACT(DAY FROM s.sample_time)	
	FROM
		performance_aggregate a, performance_sample s, entity e, entity_configuration c, entity_configuration_cpu u
	WHERE
		s.id = a.sample_id AND
		s.uptimehost_id = e.entity_id AND
		e.entity_id = c.entity_id AND
		c.entity_configuration_id = u.entity_configuration_id AND
		s.sample_time > ADD_MONTHS(SYSDATE,-$time_frame) AND
		e.entity_id = $vmware_object_id

	GROUP BY
		e.entity_id,
		e.display_name,
		s.sample_time,
		c.numcpus,
		u.mhz,
		EXTRACT(YEAR FROM s.sample_time),
		EXTRACT(MONTH FROM s.sample_time), 
		EXTRACT(DAY FROM s.sample_time)
	
	";

	$mssql="
	SELECT
		e.entity_id,
		e.display_name as NAME,
		CONVERT(date, s.sample_time) as SAMPLE_TIME,
		min(a.cpu_usr + a.cpu_sys + a.cpu_wio) as MIN_CPU_USAGE,
		max(a.cpu_usr + a.cpu_sys + a.cpu_wio) as MAX_CPU_USAGE,
		avg(a.cpu_usr + a.cpu_sys + a.cpu_wio) as AVG_CPU_USAGE,
		c.numcpus as NUM_CPU,
		u.mhz as TOTAL_MHZ,
		day(s.sample_time),
		month(s.sample_time),
		year(s.sample_time)
	FROM
		performance_aggregate a, performance_sample s, entity e, entity_configuration c, entity_configuration_cpu u
	WHERE
		s.id = a.sample_id AND
		s.uptimehost_id = e.entity_id AND
		e.entity_id = c.entity_id AND
		c.entity_configuration_id = u.entity_configuration_id AND
		s.sample_time > dateadd(month,-$time_frame,getdate()) AND
		e.entity_id = $vmware_object_id
	GROUP BY
		e.entity_id,
		e.display_name,
		s.sample_time,
		c.numcpus,
		u.mhz,
		year(s.sample_time),
		month(s.sample_time),
		day(s.sample_time)";
	
	$mysql= " 
	SELECT
		e.entity_id,
		e.display_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		min(a.cpu_usr + a.cpu_sys + a.cpu_wio) as MIN_CPU_USAGE,
		max(a.cpu_usr + a.cpu_sys + a.cpu_wio) as MAX_CPU_USAGE,
		avg(a.cpu_usr + a.cpu_sys + a.cpu_wio) as AVG_CPU_USAGE,
		c.numcpus as NUM_CPU,
		u.mhz as TOTAL_MHZ,
		day(s.sample_time),
		month(s.sample_time),
		year(s.sample_time)
	FROM
		performance_aggregate a, performance_sample s, entity e, entity_configuration c, entity_configuration_cpu u
	WHERE
		s.id = a.sample_id AND
		s.uptimehost_id = e.entity_id AND
		e.entity_id = c.entity_id AND
		c.entity_configuration_id = u.entity_configuration_id AND
		s.sample_time > date_sub(now(),interval  ". $time_frame . " month) AND
		e.entity_id = $vmware_object_id
	GROUP BY
		e.entity_id,
		e.display_name,
		s.sample_time,
		c.numcpus,
		u.mhz,
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

	$name = $hostCpuResults[0]['NAME'];
	$cpuScale = 1;

	foreach ((array)$hostCpuResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME'])-$offset;
		$x = $sample_time * 1000;

		$data = array($x, floatval($row['MIN_CPU_USAGE'] / $cpuScale));
		array_push($min_cpu_usage_array, $data);

		$data = array($x, floatval($row['MAX_CPU_USAGE'] / $cpuScale));
		array_push($max_cpu_usage_array, $data);

		$data = array($x, floatval($row['AVG_CPU_USAGE'] / $cpuScale));
		array_push($avg_cpu_usage_array, $data);
	}

	$capacity = floatval((100 * $hostCpuResults[0]['NUM_CPU'] ) / $cpuScale);

	if ($metricType == 'min')
	{
		$my_series = array(
			'name' => $name . " - Daily Cpu Min",
			'capacity' => $capacity,
			'unit' => '%',
			'series' => $min_cpu_usage_array
			);
	}

	if ($metricType == 'max')
	{
		$my_series = array(
			'name' => $name . " - Daily Cpu Max",
			'capacity' => $capacity,
			'unit' => '%',
			'series' => $max_cpu_usage_array
			);
	}

	if ($metricType == 'avg')
	{
		$my_series = array(
			'name' => $name . " - Daily Cpu Avg",
			'capacity' => $capacity,
			'unit' => '%',
			'series' => $avg_cpu_usage_array
			);
	}

	if (count((array)$my_series['series']) > 0)
	{
		array_push($json, $my_series);
	}
	if (count((array)$json) > 0)
	{
		echo json_encode($json);
	}
	else
	{
		echo "No Data";
	}

}



elseif ( $query_type == "osperf-Filesystem")
{

	$min_datastore_usage_array = array();
	$max_datastore_usage_array = array();
	$avg_datastore_usage_array = array();
	$min_datastore_prov_array = array();
	$max_datastore_prov_array = array();
	$avg_datastore_prov_array = array();
	$datastoreResults = array();

	$datastoreOracleSql="
	SELECT
		e.display_name as NAME,
		CAST(s.sample_time AS DATE) as SAMPLE_TIME,
		sum(a.total_size) as TOTAL_CAPACITY,
		sum(a.total_size) as TOTALSIZE ,
		min(a.space_used) as MIN_FILESYS_USAGE,
		max(a.space_used) as MAX_FILESYS_USAGE,
		avg(a.space_used) as AVG_FILESYS_USAGE
	FROM
		performance_fscap a, performance_sample s, entity e
	WHERE
		s.id = a.sample_id AND
		s.uptimehost_id = e.entity_id AND
		s.sample_time > ADD_MONTHS(SYSDATE,-$time_frame) AND
		e.entity_id = $vmware_object_id
	GROUP BY
		sample_id,
		e.display_name,
		s.sample_time";
		
	$datastoremySql = "
	SELECT
		e.display_name as NAME,
		date(s.sample_time) as SAMPLE_TIME,
		sum(a.total_size) as TOTAL_CAPACITY,
		sum(a.total_size) as TOTALSIZE ,
		min(a.space_used) as MIN_FILESYS_USAGE,
		max(a.space_used) as MAX_FILESYS_USAGE,
		avg(a.space_used) as AVG_FILESYS_USAGE
	FROM
		performance_fscap a, performance_sample s, entity e
	WHERE
		s.id = a.sample_id AND
		s.uptimehost_id = e.entity_id AND
		s.sample_time > date_sub(now(),interval ". $time_frame . " month) AND
		e.entity_id = $vmware_object_id
	GROUP BY
		sample_id";

		$datastoreMsSql = "
		SELECT
			e.display_name as NAME,
			CONVERT(date, s.sample_time) as SAMPLE_TIME,
			sum(a.total_size) as TOTAL_CAPACITY,
			sum(a.total_size) as TOTALSIZE ,
			min(a.space_used) as MIN_FILESYS_USAGE,
			max(a.space_used) as MAX_FILESYS_USAGE,
			avg(a.space_used) as AVG_FILESYS_USAGE
		FROM
			performance_fscap a, performance_sample s, entity e
		WHERE
			s.id = a.sample_id AND
			s.uptimehost_id = e.entity_id AND
			s.sample_time > dateadd(month,-$time_frame,getdate())AND
			e.entity_id = $vmware_object_id
		GROUP BY
		sample_id,
		e.display_name,
		s.sample_time";

		if ($db->dbType == 'mysql'){
			$datastoreResults = $db->execQuery($datastoremySql);
		} else if ($db->dbType == 'mssql'){
			$datastoreResults = $db->execQuery($datastoreMsSql);
		} else {
			$datastoreResults = $db->execQuery($datastoreOracleSql);
		}
	$total_size= $datastoreResults[0]['TOTALSIZE'];
	$name = $datastoreResults[0]['NAME'];


	$capacity = floatval($datastoreResults[0]['TOTAL_CAPACITY']);





		foreach ((array)$datastoreResults as $index => $row) {
		$sample_time = strtotime($row['SAMPLE_TIME'])-$offset;
		$x = $sample_time * 1000;

		$data = array($x, floatval($row['MIN_FILESYS_USAGE']));
		array_push($min_datastore_usage_array, $data);

		$data = array($x, floatval($row['MAX_FILESYS_USAGE']));
		array_push($max_datastore_usage_array, $data);

		$data = array($x, floatval($row['AVG_FILESYS_USAGE']));
		array_push($avg_datastore_usage_array, $data);

	}


	if ($metricType == 'min')
	{
		$usage_series = array(
			'name' => $name . " - Daily Actual Min",
			'capacity' => $capacity,
			'series' => $min_datastore_usage_array
			);
		$prov_series = array(
			'name' => $name . " - Daily Provisioned Min",
			'capacity' => $capacity,
			'series' => $min_datastore_prov_array
			);
	}

	if ($metricType == 'max')
	{
		$usage_series = array(
			'name' => $name . " - Daily Max",
			'capacity' => $capacity,
			'series' => $max_datastore_usage_array
			);
		$prov_series = array(
			'name' => $name . " - Daily Provisioned Max",
			'capacity' => $capacity,
			'series' => $max_datastore_prov_array
			);
	}

	if ($metricType == 'avg')
	{
		$usage_series = array(
			'name' => $name . " - Daily Actual Avg",
			'capacity' => $capacity,
			'series' => $avg_datastore_usage_array
			);
		$prov_series = array(
			'name' => $name . " - Daily Provisioned Avg",
			'capacity' => $capacity,
			'series' => $avg_datastore_prov_array
			);
	}

	if (count((array)$usage_series['series']) > 0)
	{
		array_push($json, $usage_series);
	}
	if (count((array)$json) > 0)
	{
		echo json_encode($json);
	}
	else
	{
		echo "No Data";
	}
}

// Unsupported request
else {echo "Error: Unsupported Request '$query_type'" . "</br>";}

// close sessions
$db->closeDB();
?>
