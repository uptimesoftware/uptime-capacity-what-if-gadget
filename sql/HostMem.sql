SELECT 
	s.vmware_object_id, 
	o.vmware_name,
	date(s.sample_time),
	min(a.memory_usage),
	max(a.memory_usage),
	avg(a.memory_usage),
	min(a.memory_total),
	max(a.memory_total),
	avg(a.memory_total),
	day(s.sample_time), 
	month(s.sample_time), 
	year(s.sample_time) 
FROM 
	vmware_perf_aggregate a, vmware_perf_sample s, vmware_object o
WHERE 
	s.sample_id = a.sample_id AND 
	s.vmware_object_id = o.vmware_object_id AND
	s.sample_time >= '2014-09-20 14:18:01' AND 
	s.sample_time < '2014-10-23 14:18:01'  AND
	s.vmware_object_type = 'HostSystem'
	#s.vmware_object_id = 863
GROUP BY 
	s.vmware_object_id,
	year(s.sample_time),
	month(s.sample_time), 
	day(s.sample_time)

