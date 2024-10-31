(function($) {
	'use strict';

	$(document).ready(function() {
		$('#pbb_request_status_update').on('click', function(e) {
			e.preventDefault();

			const actionBtn = $(this);
			actionBtn.prop('disabled', true);

			$.ajax({
				url: dc_pbb_data.ajaxurl,
				type: "post",
				data: {
					action: "pbb_request_status_update",
					order_id: $('#post_ID').val(),
					nonce_ajax: dc_pbb_data.nonce
				},
				success: function(response) {

					if ( response.success === true ) {
						alert(response.data.updated_msg);
						location.reload();
						return false;
					}
					else {
						alert(response.data);
					}

					actionBtn.prop('disabled', false);
				}
			});
		});
	});
})(jQuery);