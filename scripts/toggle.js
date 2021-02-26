jQuery(function($) {
	$(document).on('click', '.xsx-bpu-trigger', function (e) {
		var element = $(this)
		var spinner = element.parent().children('.spinner')
		spinner.addClass('is-active')
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
				element.removeClass('dashicons-lock')
				element.removeClass('dashicons-unlock')
				element.addClass(data.data.icon)
				element.parent().attr('aria-label', data.data.aria);
				spinner.removeClass('is-active')
			},
			error    : function(request,error) {
				alert(request.responseJSON.data);
				spinner.removeClass('is-active')
			}
		});
	});
});