/* 
 * Hooks into payment methods radio buttons
 * 
 * depends on woocommerce.js
 */
jQuery(function() {
	jQuery('.woocommerce').on('change', '.payment_methods .input-radio', function() { wc_add_fees_payment_selected_id = jQuery(this).attr('id');jQuery('body').trigger('update_checkout'); });
});