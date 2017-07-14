jQuery(document).ready(function () {
    if (jQuery('input[type=radio][name=payment_method]:checked').attr('id') == 'payment_method_eway') {
        if (document.getElementById('eway-card-number') != null) {
            setTimeout(eway_add_required, 1000);
        }
    } else {
        eway_remove_required();
    }
    jQuery('input[name="payment_method"]').change(function () {
        var checked = jQuery('input[type=radio][name=payment_method]:checked').attr('id');
        if (checked == 'payment_method_eway') {
            if (jQuery("#payment_method_eway").is(':checked')) {
                setTimeout(eway_add_required, 1000);
            }
        } else {
            eway_remove_required();
        }
    });
    jQuery('input[name="payment_method"]').live('change', function () {
        if (this.id == 'payment_method_eway') {
            if (jQuery("#payment_method_eway").is(':checked')) {
                setTimeout(eway_add_required, 1000);
            }
        } else {
            eway_remove_required();
        }
    });
});

function eway_add_required() {
    jQuery('#ei_eway-card-number').prop('required', true);
    jQuery('#ei_eway-card-expiry').prop('required', true);
    jQuery('#ei_eway-card-cvc').prop('required', true);
    return true;
}

function eway_remove_required() {
    jQuery('#ei_eway-card-number').prop('required', false);
    jQuery('#ei_eway-card-expiry').prop('required', false);
    jQuery('#ei_eway-card-cvc').prop('required', false);
    jQuery('.cc-eway').css('box-shadow', 'none');
    return true;
}
