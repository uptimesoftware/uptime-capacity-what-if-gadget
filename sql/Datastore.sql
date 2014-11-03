SELECT 
	s.vmware_object_id, 
	o.vmware_name,
	date(s.sample_time),
	min(u.usage_total),
	max(u.usage_total),
	avg(u.usage_total),
	min(u.provisioned),
	max(u.provisioned),
	avg(u.provisioned),
	min(u.capacity),
	max(u.capacity),
	avg(u.capacity),
	day(s.sample_time), 
	month(s.sample_time), 
	year(s.sample_time) 
FROM 
	vmware_perf_datastore_usage u, vmware_perf_sample s, vmware_object o
WHERE 
	s.sample_id = u.sample_id AND 
	s.vmware_object_id = o.vmware_object_id AND
	s.sample_time >= '2014-07-20 14:18:01' AND 
	s.sample_time < '2014-10-23 14:18:01' AND
	s.vmware_object_type = 'Datastore'
GROUP BY 
	s.vmware_object_id,
	year(s.sample_time),
	month(s.sample_time), 
	day(s.sample_time)