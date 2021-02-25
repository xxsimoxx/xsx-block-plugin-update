jQuery(function($) {
	$(document).on('click', '.xsx-bpu-trigger', function (e) {
		var element = $(this)
		element.addClass('xsx-bpu-rotate')
		e.preventDefault();
		var plugin = element.data('file')
		$.ajax({
			url      : ajaxurl,
			type     : 'POST',
			data     : {
					action   : 'xsx_bpu_toggle',
					security : xsx_bpu_datascript.security,
					plugin   : plugin,
			},
			dataType :'json',
			success  : function(data) {
				console.log('Data OK: ' + data.data.plugin)
				element.removeClass('dashicons-lock')
				element.removeClass('dashicons-unlock')
				element.addClass(data.data.icon)
				element.removeClass('xsx-bpu-rotate')
			},
			error    : function(request,error) {
				alert("Error in AJAX request: " + request.responseJSON.data);
				element.removeClass('xsx-bpu-rotate')
			}
		});
	  });
});