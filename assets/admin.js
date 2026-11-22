(function ($) {
	'use strict';

	var $wrap = $('#woocommerce-product-data');

	function initAcf() {
		if (typeof acf === 'undefined') { return; }
		// Initialise ACF fields inside freshly AJAX-loaded variation panels.
		acf.doAction('append', $('#variable_product_options .woocommerce_variations'));
	}

	// Variations are loaded / added via AJAX.
	$wrap.on('woocommerce_variations_loaded woocommerce_variations_added', initAcf);

	// Mark a variation as changed so WooCommerce enables "Save changes".
	function markChanged($el) {
		var $v = $el.closest('.woocommerce_variation');
		if (!$v.length) { return; }
		$v.addClass('variation-needs-update');
		$('button.cancel-variation-changes, button.save-variation-changes').prop('disabled', false);
		$('#variable_product_options').trigger('woocommerce_variations_input_changed');
	}

	// Inputs: native change/input events.
	$wrap.on('change input', '.acfve-group :input', function () { markChanged($(this)); });

	// Repeaters, galleries, relationship etc. change the DOM via clicks.
	$wrap.on('click', '.acfve-group .acf-button, .acfve-group .acf-icon, .acfve-group .acf-actions a', function () {
		markChanged($(this));
	});

	// Make sure TinyMCE content is flushed to its textarea before Woo serialises the form.
	$wrap.on('click', 'button.save-variation-changes', function () {
		if (window.tinymce) { window.tinymce.triggerSave(); }
	});
})(jQuery);
