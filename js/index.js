$(document).ready(function () {
	$('.fancybox').fancybox();
	$('.fancybox').on('click', function () { loadContent(1); });
});

function loadContent(nr) {
	if (nr == 1) {
		$.ajax({
			url: 'async/LoadContent.php',
			type: 'POST',
			data: { nr : nr },
			success: function (HTML) {
				if (HTML.indexOf('@#@') == -1) {
					$('div[id*="content-"]').hide();
					$('#content-' + nr).show();
					$('#content-' + nr).html(HTML);
				} else {
					$('#message').html('<div class="error">Error: ' + HTML + '</div>');
				}
			},
			error: function (JSON) {
				$('#message').html('<div class="error">An unexpected error occurred</div>');
			}
		});
	} else if (nr == 2) {
		$.ajax({
			url: 'async/LoadContent.php',
			type: 'POST',
			data: { nr : nr, table : $('#table').val() },
			success: function (HTML) {
				if (HTML.indexOf('@#@') == -1) {
					$('div[id*="content-"]').hide();
					$('#content-' + nr).show();
					$('#content-' + nr).html(HTML);
				} else {
					$('#message').html('<div class="error">Error : ' + HTML + '</div>');
				}
			},
			error: function (JSON) {
				$('#message').html('<div class="error">An unexpected error occurred</div>');
			}
		});
	} else if (nr == 3) {
		var columns = $('.field-checkbox:checked').map(function(){return $(this).val();}).get();
		var type = $('#type').val();
		if ((type != 'xlsx') && (type != 'pdf')) {
			type = 'xlsx';
		}
		$.ajax({
			url: 'async/LoadContent.php',
			type: 'POST',
			data: { nr : nr, table : $('#table').val(), filename : $('#filename').val(), type : type, columns : columns },
			success: function (HTML) {
				if (HTML.indexOf('@#@') == -1) {
					location.href = HTML;
				} else {
					$('#message').html('<div class="error">Error : ' + HTML + '</div>');
				}
			},
			error: function (JSON) {
				$('#message').html('<div class="error">An unexpected error occurred</div>');
			}
		});
	}
}