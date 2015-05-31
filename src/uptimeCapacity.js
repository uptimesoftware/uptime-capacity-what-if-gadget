$(function() {

	var currentURL = $("script#ownScript").attr("src");
    var getMetricsPath = currentURL.substr(0,$("script#ownScript").attr("src").lastIndexOf("/")+1) + 'getmetrics.php';
    var getDropDownsPath = currentURL.substr(0,$("script#ownScript").attr("src").lastIndexOf("/")+1) + 'getdropdowns.php';
	var baseGadgetPath = currentURL.substr(0,$("script#ownScript").attr("src").lastIndexOf("/")+1);
    var newvmstring = '<div class="vm">CPU:<input type="text" id="newCpu" class="what-if-setting Cpu" name="CpuUsage" value="0" size="4">GHz - Mem:<input type="text" id="newMem" class="what-if-setting Mem" name="MemUsage" value="0" size="4">GB - X<input type="text" id="vmCount" class="what-if-setting Count" name="vmCount" value="1" size="4"><input type="button" class="remove-vm-button" value="-"></div>';

    var date = new Date();
    var uptimeOffset = date.getTimezoneOffset()*60;

    var api = new apiQueries();
    var myChart = null;
    var myChartDimensions = null;
    var uptimeCapacitySettings = {};
    var vmSettings = [];
    var divsToDim = [ '#widgetChart', '#widgetSettings' ];

	$("#widgetSettings").hide();

    $('.query-type-setting').change(queryTypeChanged);
    $('.element-status-setting').change(settingChanged);
    $('.time-frame-selector').change(settingChanged);
    $('.what-if-setting').change(settingChanged);
    $('#widgetOptions input[name=metricType]:radio').change(settingChanged);
    $('#capacitySlider').change(changeCapacityBuffer);

    $('#addVm').click(addVm);

    $('#vms').on( "click", ".remove-vm-button", removeVm);
 


    $("#closeSettings").click(function() {
        // settingChanged();      
        $("#widgetSettings").slideUp();
        // return false;           
    }); 

	uptimeGadget.registerOnEditHandler(showEditPanel);
	uptimeGadget.registerOnLoadHandler(function(onLoadData) {
		myChartDimensions = toMyChartDimensions(onLoadData.dimensions, true);
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

	function resizeGadget(dimensions) {
		myChartDimensions = toMyChartDimensions(dimensions, false);
		if (myChart) {
			myChart.resize(myChartDimensions);
		}
		$("body").height($(window).height());
	}

	function toMyChartDimensions(dimensions, initialLoad) {
		if (initialLoad)
		{
			return new UPTIME.pub.gadgets.Dimensions(Math.max(100, dimensions.width - 5), Math.max(100, dimensions.height - 5));
		}
		else
		{
			return new UPTIME.pub.gadgets.Dimensions(Math.max(100, dimensions.width - 10), Math.max(100, dimensions.height - 80));
		}
	}

    function settingChanged() {
        uptimeCapacitySettings.metricType = $("#widgetOptions input[name=metricType]:radio:checked").val();
        uptimeCapacitySettings.elementId = $('#elementId').find(":selected").val();
        uptimeCapacitySettings.timeFrame = $('#MonthSelector').find(":selected").val();
        uptimeCapacitySettings.queryType = $('#QueryTypeSelector').find(":selected").val();
        uptimeCapacitySettings.capacityBuffer = $("#capacitySlider").val();
        uptimeCapacitySettings.elementName = $('#elementId').find(":selected").text();
        uptimeCapacitySettings.vms = getAllWhatIfVMs();
        uptimeGadget.saveSettings(uptimeCapacitySettings).then(onGoodSave, onBadAjax);
  	//console.log(uptimeCapacitySettings);
    }



	function queryTypeChanged() {
		
		queryType_val = $('#QueryTypeSelector').find(":selected").val();
		queryType_split = queryType_val.split("-");


		if (queryType_split[0] == 'vmware')
		{
			if (queryType_split[1] == 'Datastore')
			{
				populateIdSelector('getVMdatastores');
			}
			else
			{
				populateIdSelector('getVMobjects');
			}
		}
		else if (queryType_split[0] == 'xenserver')
		{
			if (queryType_split[1] == 'DiskUsed')
			{
				populateIdSelector('getXenServerDatastores');
			}
			else {
				populateIdSelector('getXenServers');	
			}
			
		}
		else if ( queryType_split[0] == 'osperf')
		{
			populateIdSelector('getAgentSystems');
		}
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

        $("#widgetOptions input[name=chartType]").filter('[value=' + uptimeCapacitySettings.chartTypeId + ']').prop('checked', true);
        if (uptimeCapacitySettings)
        {
            setupWhatIfVMS(uptimeCapacitySettings.vms);
        }
        $("#widgetSettings").slideDown();
        $("body").height($(window).height());
        queryTypeChanged();
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

	function populateIdSelector(dropdown_querytype) {
		disableSettings();
		dropdownselector = '#elementId';
		url = getDropDownsPath + "?uptime_offset=14400&query_type=" + dropdown_querytype;
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

			if (myChart) {
				settingChanged();
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			console.log("Error with: " + url) ;
			displayStatusBar(errorThrown, "Error Loading the List of Elements from up.time Controller");
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
            if (settings.vms)
            {
                setupWhatIfVMS(settings.vms);
            }
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

        vmtotals = getWhatIfVMsTotals();

        myChart = new UPTIME.UptimeCapacityGadget({
            getMetricsPath : getMetricsPath + "?uptime_offset=" + uptimeOffset,
			baseGadgetPath : baseGadgetPath,
            dimensions : myChartDimensions,
            chartDivId : "widgetChart",
            metricType : uptimeCapacitySettings.metricType,
            queryType : uptimeCapacitySettings.queryType,
            elementId : uptimeCapacitySettings.elementId,
            timeFrame : uptimeCapacitySettings.timeFrame,
            capacityBuffer: uptimeCapacitySettings.capacityBuffer,
            newVMsAdjustment: vmtotals[uptimeCapacitySettings.queryType]
        }, displayStatusBar, clearStatusBar);

		myChart.render();
		$("body").height($(window).height());
	}

	function changeCapacityBuffer() {
		buffer = $("#capacitySlider").val();
		$("#CurCapacityBuffer").html(buffer + "%");
		settingChanged();
	}

    function getAllWhatIfVMs() {
        myvms = $("div.vm");
        myvmSettings = [];
        myvms.each( function(index, value) {
            mycpu = $(this).find('input.Cpu').val();
            mymem = $(this).find('input.Mem').val();
            count = $(this).find('input.Count').val();

            newSettingString = { 'cpu' : mycpu,
                                 'mem' : mymem,
                                 'count' : count };
            myvmSettings.push(newSettingString);

        });
        return myvmSettings;

    }

    function getWhatIfVMsTotals() {
        myvms = $("div.vm");
        var newCpuTotal = 0;
        var newMemTotal = 0;
        myvmSettings = [];
        myvms.each( function(index, value) {
            mycpu = $(this).find('input.Cpu').val();
            mymem = $(this).find('input.Mem').val();
            count = $(this).find('input.Count').val();

            newCpuTotal = newCpuTotal + (mycpu * count);
            newMemTotal = newMemTotal + (mymem * count);

        });
        
        return { 'Cpu': newCpuTotal, 'Mem': newMemTotal };
    }

    function setupWhatIfVMS(myvmsettings) {
        $("#vms").empty();
        if (myvmsettings)
        {
            $.each(myvmsettings, function (index, value) {
                newvm = '<div class="vm">CPU:<input type="text" id="newCpu" class="what-if-setting Cpu" name="CpuUsage" value="' + value['cpu'] + '" size="4">GHz ';
                newvm += '- Mem:<input type="text" id="newMem" class="what-if-setting Mem" name="MemUsage" value="' + value['mem'] + '" size="4">GB - ';
                newvm += 'X<input type="text" id="vmCount" class="what-if-setting Count" name="vmCount" value="' + value['count'] + '" size="4">';
                newvm += '<input type="button" class="remove-vm-button" value="-"></div>';
                $("#vms").append(newvm);

            });

        }
        else
        {
            $("#vms").append(newvmstring);
        }


    }

    function addVm() {
    	settingChanged();
        $("#vms").append(newvmstring);
    }

    function removeVm() {
        $(this).parent().remove();
    }


});