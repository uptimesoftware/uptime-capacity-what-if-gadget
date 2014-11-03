function apiQueries() {

	function getElements(elementId) {
		var deferred = UPTIME.pub.gadgets.promises.defer();
		$.ajax("/api/v1/elements/" + elementId, {
			cache : false
		}).done(function(data, textStatus, jqXHR) {
			deferred.resolve(data);
		}).fail(function(jqXHR, textStatus, errorThrown) {
			deferred.reject(UPTIME.pub.errors.toDisplayableJQueryAjaxError(jqXHR, textStatus, errorThrown, this));
		});
		return deferred.promise;
		
	}

	this.getElementStatus = function(elementId) {
		var deferred = UPTIME.pub.gadgets.promises.defer();
		$.ajax("/api/v1/elements/" + elementId + "/status", {
			cache : false
		}).done(function(data, textStatus, jqXHR) {
			deferred.resolve(data);
		}).fail(function(jqXHR, textStatus, errorThrown) {
			deferred.reject(UPTIME.pub.errors.toDisplayableJQueryAjaxError(jqXHR, textStatus, errorThrown, this));
		});
		return deferred.promise;
	};

	this.getAllElements = function() {
		var deferred = UPTIME.pub.gadgets.promises.defer();
		$.ajax("/api/v1/elements", {
			cache : false
		}).done(function(data, textStatus, jqXHR) {
			if (data.length == 0) {
				deferred.reject("No elements found.");
			} else {
				deferred.resolve(data);
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			deferred.reject(UPTIME.pub.errors.toDisplayableJQueryAjaxError(jqXHR, textStatus, errorThrown, this));
		});
		return deferred.promise;
	};


}