$(function() {

	var currentURL = $("script#ownScript").attr("src");
    var getMetricsPath = currentURL.substr(0,$("script#ownScript").attr("src").lastIndexOf("/")+1) + 'getmetrics.php';
    var getDropDownsPath = currentURL.substr(0,$("script#ownScript").attr("src").lastIndexOf("/")+1) + 'getdropdowns.php';

    var date = new Date();
    var uptimeOffset = date.getTimezoneOffset()*60;

	var api = new apiQueries();
	var myChart = null;
	var myChartDimensions = null;
	var uptimeCapacitySettings = {};
	var divsToDim = [ '#widgetChart', '#widgetSettings' ];

	$("#widgetSettings").hide();

	$('.query-type-setting').change(settingChanged);
	$('.element-status-setting').change(settingChanged);
	$('.time-frame-selector').change(settingChanged);
	$('#widgetOptions input[name=metricType]:radio').change(settingChanged);
	$('#capacitySlider').change(changeCapacityBuffer);


	$("#closeSettings").click(function() {
		$("#widgetSettings").slideUp();
	});

	uptimeGadget.registerOnEditHandler(showEditPanel);
	uptimeGadget.registerOnLoadHandler(function(onLoadData) {
		myChartDimensions = toMyChartDimensions(onLoadData.dimensions);
		if (onLoadData.hasPreloadedSettings()) {
			goodLoad(onLoadData.settings);
		} else {
			uptimeGadget.loadSettings().then(goodLoad, onBadAjax);
		}
	});
	uptimeGadget.registerOnResizeHandler(resizeGadget);

	escapeHtml = function(str) {
		return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
	};

	monitorCount = function(count) {
		if (count == 0) {
			return "No monitors";
		} else if (count == 1) {
			return "1 monitor";
		}
		return count + " monitors";
	};

	function resizeGadget(dimensions) {
		myChartDimensions = toMyChartDimensions(dimensions);
		if (myChart) {
			myChart.resize(myChartDimensions);
		}
		$("body").height($(window).height());
	}

	function toMyChartDimensions(dimensions) {
		return new UPTIME.pub.gadgets.Dimensions(Math.max(100, dimensions.width - 5), Math.max(100, dimensions.height - 5));
	}

	function settingChanged() {
		uptimeCapacitySettings.metricType = $("#widgetOptions input[name=metricType]:radio:checked").val();
		uptimeCapacitySettings.elementId = $('#elementId').find(":selected").val();
		uptimeCapacitySettings.timeFrame = $('#MonthSelector').find(":selected").val();
		uptimeCapacitySettings.queryType = $('#QueryTypeSelector').find(":selected").val();
		uptimeCapacitySettings.capacityBuffer = $("#capacitySlider").val();
		uptimeCapacitySettings.elementName = $('#elementId').find(":selected").text();
		uptimeGadget.saveSettings(uptimeCapacitySettings).then(onGoodSave, onBadAjax);
	}

	function displayStatusBar(error, msg) {
		gadgetDimOn();
		var statusBar = $("#statusBar");
		statusBar.empty();
		var errorBox = uptimeErrorFormatter.getErrorBox(error, msg);
		errorBox.appendTo(statusBar);
		statusBar.slideDown();
	}

	function clearStatusBar() {
		gadgetDimOff();
		var statusBar = $("#statusBar");
		statusBar.slideUp().empty();
	}

	function showEditPanel() {
		if (myChart) {
			myChart.stopTimer();
		}

		$("#widgetOptions input[name=chartType]").filter('[value=' + uptimeCapacitySettings.chartTypeId + ']').prop('checked',
				true);

		$("#widgetSettings").slideDown();
		$("body").height($(window).height());
		populateIdSelector();
	}

	function disableSettings() {
		$('.element-status-setting').prop('disabled', true);
		$('#closeButton').prop('disabled', true).addClass("ui-state-disabled");
	}

	function enableSettings() {
		$('.element-status-setting').prop('disabled', false);
		$('#closeButton').prop('disabled', false).removeClass("ui-state-disabled");
	}

	function elementSort(arg1, arg2) {
		return naturalSort(arg1.name, arg2.name);
	}

	function populateIdSelector() {
		disableSettings();
		dropdownselector = '#elementId';
		url = getDropDownsPath + "?uptime_offset=14400&query_type=getVMobjects";
		$(dropdownselector).empty().append($("<option />").val(-1).text("Loading..."));

		$.getJSON(url, function(data) {
		}).done(function(data) {
			$(dropdownselector).empty();
			clearStatusBar();
			enableSettings();
			$.each(data, function(key, val) {
				$(dropdownselector).append($("<option />").val(val).text(key));
			});

			if ( uptimeCapacitySettings.elementId)
			{
				$('#elementId').val(uptimeCapacitySettings.elementId);
			}

			if (!myChart) {
				settingChanged();
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			console.log("Error with: " + url) ;
			displayStatusBar(error, "Error Loading the List of Elements from up.time Controller");
		});


	}

	function goodLoad(settings) {
		clearStatusBar();
		if (settings) {
			$("#elementId").val(settings.elementId);
			$("#QueryTypeSelector").val(settings.queryType);
			$("#MonthSelector").val(settings.timeFrame);
			$("#capacitySlider").val(settings.capacityBuffer);
			$("#CurCapacityBuffer").html(settings.capacityBuffer + "%");
			$("#" + settings.metricType).prop("checked", true);
			$.extend(uptimeCapacitySettings, settings);
			displayChart();
		} else if (uptimeGadget.isOwner()) {
			$('#widgetChart').hide();
			showEditPanel();
		}
	}

	function onGoodSave() {
		clearStatusBar();
		displayChart();
	}

	function onBadAjax(error) {
		displayStatusBar(error, "Error Communicating with up.time");
	}

	function gadgetDimOn() {
		$.each(divsToDim, function(i, d) {
			var div = $(d);
			if (div.is(':visible') && div.css('opacity') > 0.6) {
				div.fadeTo('slow', 0.3);
			}
		});
	}

	function gadgetDimOff() {
		$.each(divsToDim, function(i, d) {
			var div = $(d);
			if (div.is(':visible') && div.css('opacity') < 0.6) {
				div.fadeTo('slow', 1);
			}
		});
	}

	function displayChart() {
		if (myChart) {
			myChart.stopTimer();
			myChart.destroy();
			myChart = null;
		}
		$("#widgetChart").show();


		myChart = new UPTIME.UptimeCapacityGadget({
			getMetricsPath : getMetricsPath + "?uptime_offset=" + uptimeOffset,
			dimensions : myChartDimensions,
			chartDivId : "widgetChart",
			metricType : uptimeCapacitySettings.metricType,
			queryType : uptimeCapacitySettings.queryType,
			elementId : uptimeCapacitySettings.elementId,
			timeFrame : uptimeCapacitySettings.timeFrame,
			capacityBuffer: uptimeCapacitySettings.capacityBuffer
		}, displayStatusBar, clearStatusBar);

		myChart.render();
		$("body").height($(window).height());
	}

	function changeCapacityBuffer() {
		buffer = $("#capacitySlider").val();
		$("#CurCapacityBuffer").html(buffer + "%");
		settingChanged();
	}



});