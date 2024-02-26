$(document).ready(function() {

	/* tooltips */
	$('[data-bs-toggle="tooltip"]').tooltip();

	/* toasts - flashes */
	$('.toast').toast("show");

	/* tabs nav */

	/* nette ajax */
	naja.initialize();
		/*
		naja.makeRequest('GET', url, data = null, options = {})
		.then((payload) => {
			console.log('success');
		})
		.catch((error) => {
			console.log(error);
		});
		*/

	setTimeout(function () {
		$('body').removeClass('loader');
		$('body').addClass('loaded');
	}, 100);

	$('.bg-loader').click(function(E) {
		E.stopPropagation();
		E.preventDefault();
	});

	$('.data-loader').click(function(E) {
		$('body').addClass('loader');
		$('body').removeClass('loaded');
	});

	$('[data-confirm]').click(function(E) {
		E.stopPropagation();
		E.preventDefault();

		var cfmModal = $('#confirmModal');

		var cText = $(this).data('confirm');
		var cHref = $(this).attr('href');

		cfmModal.find('.modal-body').html(cText);
		cfmModal.find('.modal-footer .data-loader').attr('href', cHref);

	});

});
