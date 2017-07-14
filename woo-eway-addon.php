<?php
/**
 * Plugin Name: Woo Eway Addon
 * Plugin URI : Woo Eway Addon
 * Description: Woo Eway Addon allows you to accept payments on your Woocommerce store. It accpets credit card payments and processes them securely with your merchant account.
 * Version:           1.0.4
 * WC requires at least: 2.3
 * WC tested up to: 2.6+
 * Requires at least: 4.0+
 * Tested up to: 4.6
 * Contributors: wp_estatic
 * Author:            Estatic Infotech Pvt Ltd
 * Author URI:        http://estatic-infotech.com/
 * License:           GPLv3
 * @package WooCommerce
 * @category Woocommerce Payment Gateway
 */
require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    deactivate_plugins(plugin_basename(__FILE__));
    add_action('load-plugins.php', function() {
        add_filter('gettext', 'change_eway_text', 99, 3);
    });

    function change_eway_text($translated_text, $untranslated_text, $domain) {
        $old = array(
            "Plugin <strong>activated</strong>.",
            "Selected plugins <strong>activated</strong>."
        );

        $new = "Please activate <b>Woocommerce</b> Plugin to use WooCommerce eWay Addon plugin";

        if (in_array($untranslated_text, $old, true)) {
            $translated_text = $new;
            remove_filter(current_filter(), __FUNCTION__, 99);
        }
        return $translated_text;
    }

    return FALSE;
}

add_action('plugins_loaded', 'init_your_gateway_class');

function init_your_gateway_class() {
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links_eway');
    if (class_exists('WC_Payment_Gateway') && !is_ssl()) {

        function add_action_links_eway($links) {
            $action_links = array(
                'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_eway_ei') . '" title="' . esc_attr(__('View WooCommerce Settings', 'woocommerce')) . '">' . __('Settings', 'woocommerce') . '</a>',
            );
            return array_merge($links, $action_links);
        }

        class WC_Gateway_Eway_EI extends WC_Payment_Gateway {

            function __construct() {
                $this->id = 'eway';
                $this->icon = null;
                $this->has_fields = true;
                $this->method_title = 'eway';
                $this->method_description = 'Eway Payment Gateway Plug-in for WooCommerce';
                $this->init_form_fields();
                $this->init_settings();
                $this->supports = array('default_credit_card_form', 'products', 'refunds');
                $this->eway_api_key = $this->get_option('eway_api');

                $this->eway_api_password = $this->get_option('eway_passwrod');

                $this->eway_mode = $this->get_option('eway_mode') === 'sandbox' ? true : false;

                $this->title = "Eway";
                if (is_admin()) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                    add_action('woocommerce_order_status_processing_to_cancelled', array($this, 'restore_stock_eway_cancel'), 10, 1);
                    add_action('woocommerce_order_status_completed_to_cancelled', array($this, 'restore_stock_eway_cancel'), 10, 1);
                    add_action('woocommerce_order_status_on-hold_to_cancelled', array($this, 'restore_stock_eway_cancel'), 10, 1);
                    add_action('woocommerce_order_status_processing_to_refunded', array($this, 'restore_stock_eway'), 10, 1);
                    add_action('woocommerce_order_status_completed_to_refunded', array($this, 'restore_stock_eway'), 10, 1);
                    add_action('woocommerce_order_status_on-hold_to_refunded', array($this, 'restore_stock_eway'), 10, 1);
                    add_action('woocommerce_order_status_cancelled_to_processing', array($this, 'reduce_stock_eway'), 10, 1);
                    add_action('woocommerce_order_status_cancelled_to_completed', array($this, 'reduce_stock_eway'), 10, 1);
                    add_action('woocommerce_order_status_cancelled_to_on-hold', array($this, 'reduce_stock_eway'), 10, 1);
                }
            }

            public function is_available() {

                if ($this->enabled == "yes") {

                    if (!$this->eway_mode && is_checkout()) {
                        return false;
                    }
                    // Required fields check
                    if ($this->eway_api_key || $this->eway_api_password || $this->eway_customer_id) {
                        return true;
                    }
                }
                return false;
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable / Disable', 'eway'),
                        'label' => __('Enable eWay(using credit card)', 'eway'),
                        'type' => 'checkbox',
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title' => __('Title', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('Payment title the customer will see during the checkout process.', 'eway'),
                        'default' => __('Eway', 'eway'),
                    ),
                    'eway_customer_id' => array(
                        'title' => __('eWay Customer ID', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('This is the API Login provided by eway when you signed up for an account.', 'eway'),
                    ),
                    'eway_api' => array(
                        'title' => __('eWay Rapid API key', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('This is the API Login provided by eway when you signed up for an account.', 'eway'),
                    ),
                    'eway_passwrod' => array(
                        'title' => __('eWay Rapid Password', 'eway'),
                        'type' => 'text',
                        'desc_tip' => __('This is the API Login provided by eway when you signed up for an account.', 'eway'),
                    ),
                    'eway_mode' => array(
                        'title' => __('eWay Mode', 'eway'),
                        'type' => 'select',
                        'description' => '',
                        'default' => 'sandbox',
                        'options' => array(
                            'sandbox' => __('Sandbox', 'eway'),
                            'live' => __('Live', 'eway'),
                        ),
                    ),
                    'show_accepted' => array(
                        'title' => __('Show Accepted Card Icons', 'eway'),
                        'type' => 'select',
                        'class' => 'chosen_select',
                        'css' => 'width: 350px;',
                        'desc_tip' => __('Select the mode to accept.', 'eway'),
                        'options' => array(
                            'yes' => 'Yes',
                            'no' => 'No',
                        ),
                        'default' => array('yes'),
                    ), 'eway_cardtypes' => array(
                        'title' => __('Accepted Card Types', 'eway'),
                        'type' => 'multiselect',
                        'class' => 'chosen_select',
                        'css' => 'width: 350px;',
                        'desc_tip' => __('Add/Remove credit card types to accept.', 'eway'),
                        'options' => array(
                            'mastercard' => 'MasterCard',
                            'visa' => 'Visa',
                            'dinersclub' => 'Dinners Club',
                            'amex' => 'AMEX',
                            'discover' => 'Discover',
                            'jcb' => 'JCB'
                        ),
                        'default' => array('mastercard' => 'MasterCard',
                            'visa' => 'Visa',
                            'discover' => 'Discover',
                            'amex' => 'AMEX'),
                    ),
                );
            }

            function get_card_type($number) {
                $number = preg_replace('/[^\d]/', '', $number);

                if (preg_match('/^3[47][0-9]{13}$/', $number)) {
                    $card = 'amex';
                } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
                    $card = 'dinersclub';
                } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
                    $card = 'discover';
                } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
                    $card = 'jcb';
                } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
                    $card = 'mastercard';
                } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
                    $card = 'visa';
                } else {
                    $card = 'unknown';
                }

                return $card;
            }

            public function get_icon() {

                if ($this->get_option('show_accepted') == 'yes') {

                    $get_cardtypes = $this->get_option('eway_cardtypes');
                    $icons = "";
                    foreach ($get_cardtypes as $val) {
                        $cardimage = plugins_url('images/' . $val . '.png', __FILE__);
                        $icons.='<img src="' . $cardimage . '" alt="' . $val . '" />';
                    }
                } else {
                    $icons = "";
                }
                return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
            }

            public function restore_stock_eway($order_id) {
                $order = new WC_Order($order_id);
                $refund = self::process_refund($order_id, $amount = NULL);
                if ($refund == true) {
                    $payment_method = get_post_meta($order->id, '_payment_method', true);
                    if ($payment_method == 'eway') {
                        if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
                            return;
                        }
                        foreach ($order->get_items() as $item) {

                            if ($item['product_id'] > 0) {
                                $_product = $order->get_product_from_item($item);

                                if ($_product && $_product->exists() && $_product->managing_stock()) {

                                    $old_stock = $_product->stock;

                                    $qty = apply_filters('woocommerce_order_item_quantity', $item['qty'], $this, $item);

                                    $new_quantity = $_product->increase_stock($qty);

                                    do_action('woocommerce_auto_stock_restored', $_product, $item);

                                    $order->add_order_note(sprintf(__('Item #%s stock incremented from %s to %s.', 'woocommerce'), $item['product_id'], $old_stock, $new_quantity));

                                    $order->send_stock_notifications($_product, $new_quantity, $item['qty']);
                                }
                            }
                        }
                    }
                }
            }

            public function reduce_stock_eway($order_id) {
                $order = new WC_Order($order_id);
                $payment_method = get_post_meta($order->id, '_payment_method', true);
                if ($payment_method == 'eway') {
                    if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
                        return;
                    }

                    foreach ($order->get_items() as $item) {

                        if ($item['product_id'] > 0) {
                            $_product = $order->get_product_from_item($item);

                            if ($_product && $_product->exists() && $_product->managing_stock()) {

                                $old_stock = $_product->stock;

                                $qty = apply_filters('woocommerce_order_item_quantity', $item['qty'], $this, $item);

                                $new_quantity = $_product->reduce_stock($qty);

                                do_action('woocommerce_auto_stock_restored', $_product, $item);

                                $order->add_order_note(sprintf(__('Item #%s stock reduce from %s to %s.', 'woocommerce'), $item['product_id'], $old_stock, $new_quantity));

                                $order->send_stock_notifications($_product, $new_quantity, $item['qty']);
                            }
                        }
                    }
                }
            }

            public function process_payment($order_id) {
                global $woocommerce;
                $customer_order = new WC_Order($order_id);

                require('vendor/autoload.php');
                if ($this->eway_mode == true) {
                    define('MODE_SANDBOX', 'https://api.sandbox.ewaypayments.com/AccessCode/');
                } else {
                    define('MODE_SANDBOX', 'https://api.ewaypayments.com/AccessCode/');
                }

                $apiKey = $this->eway_api_key;
                $apiPassword = $this->eway_api_password;

                if ($this->eway_mode == true) {
                    $apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
                } else {
                    $apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;
                }

                $client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);

                $cardtype = $this->get_card_type(sanitize_text_field(str_replace(' ', '', $_POST['ei_eway-card-number'])));
                if (!in_array($cardtype, $this->get_option('eway_cardtypes'))) {
                    wc_add_notice('Merchant do not accept/support payments using ' . ucwords($cardtype) . ' card', $notice_type = 'error');
                    return array(
                        'result' => 'success',
                        'redirect' => WC()->cart->get_checkout_url(),
                    );
                    die;
                }
                $card_num = sanitize_text_field(str_replace(' ', '', $_POST['ei_eway-card-number']));
                $exp_date = explode("/", sanitize_text_field($_POST['ei_eway-card-expiry']));
                $exp_month = str_replace(' ', '', $exp_date[0]);
                $exp_year = str_replace(' ', '', $exp_date[1]);
                $cvc = sanitize_text_field($_POST['ei_eway-card-cvc']);
                $currency = get_woocommerce_currency();
                $product = new WC_Product($order_id);
                $sku = $product->get_sku();
                $transaction = [
                    'Customer' => [
                        'Reference' => '1234',
                        'Title' => 'Ms',
                        'FirstName' => $customer_order->billing_first_name,
                        'LastName' => $customer_order->billing_last_name,
                        'CompanyName' => $customer_order->billing_company_name,
                        'JobDescription' => 'Product Purchase',
                        'Street1' => $customer_order->billing_address_1,
                        'Street2' => $customer_order->billing_address_2,
                        'City' => $customer_order->billing_city,
                        'State' => $customer_order->billing_state,
                        'PostalCode' => $customer_order->billing_postcode,
                        'Country' => $customer_order->billing_country,
                        'Phone' => $customer_order->billing_phone,
                        'Mobile' => $customer_order->billing_phone,
                        'Email' => $customer_order->billing_email,
                        "Url" => "http://www.ewaypayments.com",
                        'CardDetails' => [
                            'Name' => $customer_order->billing_first_name,
                            'Number' => $card_num,
                            'ExpiryMonth' => $exp_month,
                            'ExpiryYear' => $exp_year,
                            'CVN' => $cvc,
                        ]
                    ],
                    'ShippingAddress' => [
                        'ShippingMethod' => \Eway\Rapid\Enum\ShippingMethod::NEXT_DAY,
                        'FirstName' => $customer_order->shipping_first_name,
                        'LastName' => $customer_order->shipping_last_name,
                        'Street1' => $customer_order->shipping_address_1,
                        'Street2' => $customer_order->shipping_address_2,
                        'City' => $customer_order->shipping_city,
                        'State' => $customer_order->shipping_state,
                        'Country' => $customer_order->shipping_country,
                        'PostalCode' => $customer_order->shipping_postcode,
                        'Phone' => $customer_order->shipping_phone,
                    ],
                    'Items' => [
                        [
                            'SKU' => $sku,
                            'Description' => 'Item Description 1',
                            'Quantity' => $customer_order->get_order_number(),
                            'UnitCost' => '',
                            'Tax' => $customer_order->get_total_tax(),
                        ],
                    ],
                    'Payment' => [
                        'TotalAmount' => floatval($customer_order->order_total * 100),
                        'InvoiceNumber' => 'Inv ' . $order_id,
                        'InvoiceDescription' => 'Individual Invoice Description',
                        'InvoiceReference' => $order_id,
                        'CurrencyCode' => $currency,
                    ],
                    'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
                    'Capture' => true,
                ];
                $response = $client->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);
                if ($response->TransactionStatus == true) {
                    add_post_meta($order_id, '_transaction_id', $response->TransactionID);
                    $customer_order->add_order_note(__('eWay payment completed.', 'eway'));
                    $customer_order->payment_complete();

                    $woocommerce->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($customer_order),
                    );
                } else {
                    $errors = split(', ', $response->ResponseMessage);
                    $k = "";

                    foreach ($response->getErrors() as $error) {
                        $k.=\Eway\Rapid::getMessage($error) . "<br>";
                    }
                    wc_add_notice('Error Processing Checkout, please check the errors' . $k . "<br>", 'error');
                    return array(
                        'result' => 'success',
                        'redirect' => WC()->cart->get_checkout_url(),
                    );
                    die;
                }
            }

            public function restore_stock_eway_cancel($order_id) {
                $order = new WC_Order($order_id);
  
$payment_method = get_post_meta($order->id, '_payment_method', true);
if ($payment_method == 'eway') {
                    if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
                        return;
                    }
                    foreach ($order->get_items() as $item) {

                        if ($item['product_id'] > 0) {
                            $_product = $order->get_product_from_item($item);

                            if ($_product && $_product->exists() && $_product->managing_stock()) {

                                $old_stock = $_product->stock;

                                $qty = apply_filters('woocommerce_order_item_quantity', $item['qty'], $this, $item);

                                $new_quantity = $_product->increase_stock($qty);

                                do_action('woocommerce_auto_stock_restored', $_product, $item);

                                $order->add_order_note(sprintf(__('Item #%s stock incremented from %s to %s.', 'woocommerce'), $item['product_id'], $old_stock, $new_quantity));

                                $order->send_stock_notifications($_product, $new_quantity, $item['qty']);
                            }
                        }
                    }
                }
            }

            /* Start of credit card form */

            public function payment_fields() {
                echo apply_filters('wc_eway_description', wpautop(wp_kses_post(wptexturize(trim($this->method_description)))));
                $this->form();
            }

            public function field_name($name) {
                return $this->supports('tokenization') ? '' : ' name="ei_' . esc_attr($this->id . '-' . $name) . '" ';
            }

            public function form() {
                wp_enqueue_script('wc-credit-card-form');
                $fields = array();
                $cvc_field = '<p class="form-row form-row-last">
	<label for="ei_eway-card-cvc">' . __('Card Code', 'woocommerce') . ' <span class="required">*</span></label>
	<input id="ei_eway-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' />
</p>';
                $default_fields = array(
                    'card-number-field' => '<p class="form-row form-row-wide">
	<label for="ei_eway-card-number">' . __('Card Number', 'woocommerce') . ' <span class="required">*</span></label>
	<input id="ei_eway-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
</p>',
                    'card-expiry-field' => '<p class="form-row form-row-first">
<label for="ei_eway-card-expiry">' . __('Expiry (MM/YY)', 'woocommerce') . ' <span class="required">*</span></label>
<input id="ei_eway-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
</p>',
                    'card-cvc-field' => $cvc_field
                );

                $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
                ?>

                <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
                    <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
                    <?php
                    foreach ($fields as $field) {
                        echo $field;
                    }
                    ?>
                    <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
                    <div class="clear"></div>
                </fieldset>
                <?php
            }

            public function process_refund($order_id, $amount = NULL, $reason = '') {
                global $woocommerce;
                $customer_order = new WC_Order($order_id);
                require('vendor/autoload.php');
                $transaction_id = get_post_meta($order_id, '_transaction_id', true);

                if ($amount > 0) {
                    if ($this->eway_mode == true) {
                        define('MODE_SANDBOX', 'https://api.sandbox.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    } else {
                        define('MODE_SANDBOX', 'https://api.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    }

                    $apiKey = $this->eway_api_key;
                    $apiPassword = $this->eway_api_password;

                    if ($this->eway_mode == true) {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
                    } else {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;
                    }
                    $client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);
                    $refund = [
                        'Refund' => [
                            'TransactionID' => $transaction_id,
                            'TotalAmount' => $amount * 100
                        ],
                    ];
                    if ($refund) {
                        $repoch = $refund->created;
                        $rdt = new DateTime($repoch);
                        $rtimestamp = $rdt->format('Y-m-d H:i:s e');
                        $response = $client->refund($refund);
                        if ($response->TransactionStatus == true) {
                            $customer_order->add_order_note(__('Eway Refund completed at. ' . $rtimestamp . ' with Refund ID = ' . $response->TransactionID, 'eway'));
                            return true;
                        } else {
                            if ($response->getErrors()) {
                                foreach ($response->getErrors() as $error) {
                                    throw new Exception(__("Error: " . \Eway\Rapid::getMessage($error)));
                                }
                            } else {
                                throw new Exception(__('Sorry, your refund failed'));
                            }
                            return false;
                        }
                    }
                } else {
                    if ($this->eway_mode == true) {
                        define('MODE_SANDBOX', 'https://api.sandbox.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    } else {
                        define('MODE_SANDBOX', 'https://api.ewaypayments.com/Transaction/' . $transaction_id . '/Refund');
                    }

                    $apiKey = $this->eway_api_key;
                    $apiPassword = $this->eway_api_password;

                    if ($this->eway_mode == true) {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
                    } else {
                        $apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;
                    }
                    $client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);
                    $order_total = get_post_meta($order_id, '_order_total', true);
                    $refund = [
                        'Refund' => [
                            'TransactionID' => $transaction_id,
                            'TotalAmount' => $order_total * 100
                        ],
                    ];
                    if ($refund) {
                        $repoch = $refund->created;
                        $rdt = new DateTime($repoch);
                        $rtimestamp = $rdt->format('Y-m-d H:i:s e');
                        $response = $client->refund($refund);

                        if ($response->TransactionStatus == true) {
                            $customer_order->add_order_note(__('Eway Refund completed at. ' . $rtimestamp . ' with Refund ID = ' . $response->TransactionID, 'eway'));
                            return true;
                        } else {
                            if ($response->getErrors()) {
                                foreach ($response->getErrors() as $error) {
                                    throw new Exception(__("Error: " . \Eway\Rapid::getMessage($error)));
                                }
                            } else {
                                throw new Exception(__('Sorry, your refund failed'));
                            }
                            return false;
                        }
                    }
                }
            }

        }

    } else {

        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', 'activate_error1');
        }
        if (!is_ssl()) {
            add_action('admin_notices', 'sslerror1');
        }

        deactivate_plugins(plugin_basename(__FILE__));
        return FALSE;
    }
}

function activate_error1() {
    $html = '<div class="error">';
    $html .= '<p>';
    $html .= __('Please activate <b>Woocommerce</b> Plugin to use this plugin');
    $html .= '</p>';
    $html .= '</div>';
    echo $html;
}

function sslerror1() {
    $html = '<div class="error">';
    $html .= '<p>';
    $html .= __('Please use <b>ssl</b> and activate Force secure checkout to use this plugin');
    $html .= '</p>';
    $html .= '</div>';
    echo $html;
}

function add_eway_gateway_class($methods) {
    $methods[] = 'WC_Gateway_Eway_EI';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_eway_gateway_class');

function add_custom_js_eway() {
    wp_enqueue_script('jquery-cc-eway', plugin_dir_url(__FILE__) . 'js/cc.custom_eway.js', array('jquery'), '1.0', True);
}

add_action('wp_enqueue_scripts', 'add_custom_js_eway');

