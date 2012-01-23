jQuery(document).ready(function($) {
	$('.kforms-remove-notification').click(function() {
		var nid = $(this).attr('id').substr(27);
		$('tr#kforms-edit-notifications-' + nid).remove();
	});
});