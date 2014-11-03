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
        	

        	//let see if we can just figure out when Xvalue = Capacity and then the date to go with it
        	capacityLeft = capacityCap - last_Xvalue;
        	timeToGo = capacityLeft / xDelta;
        	timeToGoInMS = timeToGo * yDelta;
        	actualTime = timeToGoInMS + last_Yvalue;

        	CapacityLine = [[firstPoint[0], capacityCap],
							[lastPoint[0], capacityCap]];

			BufferedCapacityLine = [[firstPoint[0], capacityCapBuffered],
									[lastPoint[0], capacityCapBuffered]];

			LineOfBestFitForEstimatedMetrics = [lastPoint];

        	if ( xDelta > 0 && capacityCap > lastPoint[1])
   			{
   			   	BufferedCapacityPoint = figureOutCapacity(capacityCapBuffered, last_Xvalue, last_Yvalue, xDelta, yDelta);
        		CapacityPoint = figureOutCapacity(capacityCap, last_Xvalue, last_Yvalue, xDelta, yDelta);
        	
        		CapacityLine.push(CapacityPoint);
        		BufferedCapacityLine.push(BufferedCapacityPoint);


				countDowntillDoomsday(lastPoint, CapacityPoint, BufferedCapacityPoint, xDelta);

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
				justAddTitletoDoomsday();
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
		}

		function countDowntillDoomsday(startpoint, capacityPoint, bufferedcapacityPoint, Delta)
		{
			$("#countDownTillDoomsDay").html("");
			starttime = startpoint[0];
			endtime = capacityPoint[0];

			//regular capacity
			time_left =  (endtime - starttime);
			time_left_in_days_till_Cap = Math.round(time_left / 1000 / 60 / 60 / 24);

			//buffered capacity
			endtime = bufferedcapacityPoint[0];
			time_left =  (endtime - starttime);
			time_left_in_days_till_BuffedCap = Math.round(time_left / 1000 / 60 / 60 / 24);


			overview_string = "";
			overview_string += "" + metricType + " " + queryType + " usage over " + timeFrame + " months<br>"
			overview_string += "Days left till Capacity: " + time_left_in_days_till_Cap + "<br>";
			overview_string += "Days left till Buffered Capacity: " + time_left_in_days_till_BuffedCap + "<br>";
			overview_string += "Average Daily Growth: " + Delta;
			$("#countDownTillDoomsDay").html(overview_string);
		}

		function justAddTitletoDoomsday()
		{
			$("#countDownTillDoomsDay").html("");

			overview_string = "Looking at " + metricType + " " + queryType + " usage over " + timeFrame + " months<br>"
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