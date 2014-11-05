if (typeof UPTIME == "undefined") {
	var UPTIME = {};
}

if (typeof UPTIME.UptimeCapacityGadget == "undefined") {
	UPTIME.UptimeCapacityGadget = function(options, displayStatusBar, clearStatusBar) {
		Highcharts.setOptions({
			global : {
				useUTC : false
			}
		});

		var dimensions = new UPTIME.pub.gadgets.Dimensions(100, 100);
		var chartDivId = null;
		var elementId = null;
		var metricType = null;
		var queryType = null;
		var timeFrame = null;
		var chartTimer = null;
        var newVMsAdjustment = 0;
		var capacityBuffer = 100;
		var api = new apiQueries();
		var getMetricsPath = null;

		var textStyle = {
			fontFamily : "Verdana, Arial, Helvetica, sans-serif",
			fontSize : "9px",
			lineHeight : "11px",
			color : "#565E6C"
		};

		if (typeof options == "object") {
			dimensions = options.dimensions;
			chartDivId = options.chartDivId;
			metricType = options.metricType;
			queryType = options.queryType;
			elementId = options.elementId;
			timeFrame = options.timeFrame;
			capacityBuffer = options.capacityBuffer;
			getMetricsPath = options.getMetricsPath;
            newVMsAdjustment = options.newVMsAdjustment;
		}

		var dataLabelsEnabled = false;
		var chart = new Highcharts.Chart({
			chart : {
				renderTo: 'widgetChart',
                type: 'line',
                style: {fontFamily: 'Arial',
                    fontSize: '9px'},
                spacingTop: 10,
                spacingBottom: 10},
            	title: {text: ""},
            	credits: {enabled: false},
            	xAxis: {type: 'datetime',
    	            title: {enabled: true,
        	        text: ""}},
            	yAxis: {min: 0,
            	    title: {enabled: false,
                    text: ""}},
            	plotOptions: {spline: {marker: {enabled: false}},
                    areaspline: {marker: {enabled: false}}},
            	series: [],
		});

		function requestData() {

			var firstPoint = null;
			var lastPoint = null;
			var my_url = getMetricsPath + '&query_type=' + queryType + '&metricType='  + metricType + "&element=" + elementId + "&time_frame=" + timeFrame;
		    $.ajax({
		        'async': true,
		        'global': false,
		        'url': my_url,
		        'dataType': "json",
		        'success': function (data) {

		        	$.each(data, function(index, value) {
		            	chart.addSeries({
		            		name: value['name'],
		            		data: value['series']
						});

						addCapacityLines(value);
					});

		        	clearStatusBar();
					dataLabelsEnabled = true;
					chart.hideLoading();
		        },
		        'error': function () {
		        	$("#countDownTillDoomsDay").html("No Data");
		        	chart.hideLoading();
		        }
	    	});	
		}

		function addCapacityLines(data) {

			firstPoint = data['series'][0];

			valueLength = data['series'].length - 1;
			lastPoint = data['series'][valueLength];

			name = data['name'];


        	timeseries = data['series'];


        	xDeltaTotal = 0;
        	yDeltaTotal = 0;

        	$.each(timeseries, function(index, value) {
        		if (index >= 1)
        		{
        			xDelta = value[1] - timeseries[index - 1][1];
        			yDelta = value[0] - timeseries[index - 1][0];
        			xDeltaTotal = xDeltaTotal + xDelta;
        			yDeltaTotal = yDeltaTotal + yDelta;
        		}
        	});


        	xDelta = xDeltaTotal / (timeseries.length -1);
        	yDelta = yDeltaTotal / (timeseries.length -1);

        	capacityCap = data['capacity'];
        	capacityCapBuffered = data['capacity'] * ( capacityBuffer / 100);

        	LineOfBestFitForRealMetrics = [firstPoint, lastPoint];


     		last_Xvalue = lastPoint[1];
        	last_Yvalue = lastPoint[0];
        	
        	CapacityLine = [[firstPoint[0], capacityCap],
							[lastPoint[0], capacityCap]];

			BufferedCapacityLine = [[firstPoint[0], capacityCapBuffered],
									[lastPoint[0], capacityCapBuffered]];

			LineOfBestFitForEstimatedMetrics = [lastPoint];

		    if (newVMsAdjustment > 0 && xDelta > 0)
            {
                EstimateLineWithNewVms = [[lastPoint[0], lastPoint[1] + newVMsAdjustment ]];
                capacityWithNewVms = figureOutCapacity(capacityCap, EstimateLineWithNewVms[0][1], last_Yvalue , xDelta, yDelta);

                bufferedcapacityWithNewVms = figureOutCapacity(capacityCapBuffered, EstimateLineWithNewVms[0][1], last_Yvalue, xDelta, yDelta);
                
                EstimateLineWithNewVms.push(capacityWithNewVms, bufferedcapacityWithNewVms);
            }



        	if ( xDelta > 0 && capacityCap > lastPoint[1])
   			{
   			   	BufferedCapacityPoint = figureOutCapacity(capacityCapBuffered, last_Xvalue, last_Yvalue, xDelta, yDelta);
        		CapacityPoint = figureOutCapacity(capacityCap, last_Xvalue, last_Yvalue, xDelta, yDelta);
        	
        		CapacityLine.push(CapacityPoint);
        		BufferedCapacityLine.push(BufferedCapacityPoint);



				countDowntillDoomsday(lastPoint, CapacityPoint, BufferedCapacityPoint, xDelta, newVMsAdjustment, data['unit']);

				if (BufferedCapacityPoint[0] > CapacityPoint[0])
				{
					LineOfBestFitForEstimatedMetrics.push(CapacityPoint);
					LineOfBestFitForEstimatedMetrics.push(BufferedCapacityPoint);
					CapacityLine.push([BufferedCapacityPoint[0], CapacityPoint[1]]);

				}
				else
				{
					LineOfBestFitForEstimatedMetrics.push(BufferedCapacityPoint);
					LineOfBestFitForEstimatedMetrics.push(CapacityPoint);
					BufferedCapacityLine.push([CapacityPoint[0], BufferedCapacityPoint[1]]);
				}
			}
			else
			{
				justAddTitletoDoomsday(xDelta, data['unit']);
			}


        	chart.addSeries({
        		name: "Capacity",
        		data: CapacityLine
        	});

        	chart.addSeries({
        		name: "Buffered Capacity",
        		data: BufferedCapacityLine
        	});

			chart.addSeries({
        		name: name + " - Usage",
        		data: LineOfBestFitForRealMetrics
        	});

        	chart.addSeries({
        		name: name + " - Est",
        		data: LineOfBestFitForEstimatedMetrics
        	});

            if (newVMsAdjustment > 0 && xDelta > 0)
            {
                console.log(EstimateLineWithNewVms);
                chart.addSeries({
                    name: name + " - Est With New VMs",
                    data: EstimateLineWithNewVms
                });
            }
		}

		function countDowntillDoomsday(startpoint, capacityPoint, bufferedcapacityPoint, Delta, VmsAdjustment, unit)
		{
			$("#countDownTillDoomsDay").html("");
			starttime = startpoint[0];
			endtime = capacityPoint[0];

			//real capacity at current growth
			time_left =  (endtime - starttime);
			time_left_in_days_till_Cap = Math.round(time_left / 1000 / 60 / 60 / 24);

			//real capacity with new VMs
			time_left =  (endtime - starttime);
			time_left_in_days_till_Cap_with_New_VMs = Math.round(time_left / 1000 / 60 / 60 / 24);

			//buffered capacity at current growth
			endtime = bufferedcapacityPoint[0];
			time_left =  (endtime - starttime);
			time_left_in_days_till_BuffedCap = Math.round(time_left / 1000 / 60 / 60 / 24);

			//buffered capacity with new VMs
			endtime = bufferedcapacityPoint[0];
			time_left =  (endtime - starttime);
			time_left_in_days_till_BuffedCap_with_New_VMs = Math.round(time_left / 1000 / 60 / 60 / 24);


			overview_string = "";
			overview_string += '<div id="infoTitle">' + metricType + " " + queryType + " usage over " + timeFrame + " months</div><br>"

			overview_string += '<div id="infoText">Average Daily Growth: ' + Delta.toFixed(2) + " " + unit + "</br></br>";
			overview_string += '<div id="infoCol1"> Days left till Real Capacity at current Growth: ' + time_left_in_days_till_Cap + "<br>";
			overview_string += "Days left till Buffered Capacity current Growth: " + time_left_in_days_till_BuffedCap + "<br></div>";

			if (VmsAdjustment > 0)
			{
		
				overview_string += '<div id="infoCol2"> Days left till Real Capacity with New VMs: ' + time_left_in_days_till_Cap_with_New_VMs + "<br>";
				overview_string += "Days left till Buffered Capacity with New VMs: " + time_left_in_days_till_BuffedCap_with_New_VMs + "<br></div>";
			}
			overview_string += "</div>";
	
			$("#countDownTillDoomsDay").html(overview_string);
		}

		function justAddTitletoDoomsday(Delta, unit)
		{
			$("#countDownTillDoomsDay").html("");

			overview_string = '<div id="infoTitle">' + metricType + " " + queryType + " usage over " + timeFrame + " months</div><br>";
			overview_string +=  '<div id="infoText">Usage Trending Downwards at ' + Delta.toFixed(2) + " " + unit + "</div>";
			$("#countDownTillDoomsDay").html(overview_string);

		}

		function figureOutCapacity( targetCapacity, startX, startY, deltaX, deltaY )
		{

			CapacityLeft = targetCapacity - startX;
        	timeToGo = CapacityLeft / deltaX;
        	timeToGoInMS = timeToGo * deltaY;
        	actualTime = timeToGoInMS + startY;

        	return [actualTime, targetCapacity ];

		}

		// public functions for this function/class
		var publicFns = {
			render : function() {
				chart.showLoading();
				requestData();
			},
			resize : function(dimensions) {
				chart.setSize(dimensions.width, dimensions.height);
			},
			stopTimer : function() {
				if (chartTimer) {
					window.clearTimeout(chartTimer);
				}
			},
			destroy : function() {
				chart.destroy();
			}
		};
		return publicFns; // Important: we need to return the public
		// functions/methods

	};
}