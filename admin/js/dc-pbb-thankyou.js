(function($) {
	'use strict';

	$(document).ready(function() {

		$('.copy-pbb-rf-code').on('click', function(){

			const rfCodeEl = $(this).find('.pbb-rf-code');
			const rfCodeText = rfCodeEl.text();
			const copyBtn = $(this).find('.pbb-rf-copy');

			let $temp_input = $('<textarea style="opacity:0">');
			$('body').append($temp_input);
			$temp_input.val(rfCodeText).trigger('select');

			document.execCommand('copy');

			$temp_input.remove();

			copyBtn.css( 'background', '#5f5f5f' );
			setTimeout(function () {
				copyBtn.css( 'background', '#808080' );
			}, 200);
		});
	});
})(jQuery);