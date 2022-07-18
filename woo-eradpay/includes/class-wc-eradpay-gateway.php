<?php
/**
 * WooCommerce eradPay Gateway
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (! class_exists('WC_Payment_Gateway')) {
    exit; // Exit if wc payment gateway class not found
}

class WC_eradPay_Gateway extends WC_Payment_Gateway
{
    const SESSION_KEY = 'eradpay_wc_order_id';
    const ERADPAY_ORDER_ID = 'razorpay_order_id';
    const ERADPAY_TRANSACTION_ID_TOKEN = 'eradpay_transaction_id';

    const SUPPORTED_CURRENCIES_LIST = [
        'USD',
        'EUR',
        'GBP',
        'AED',
        'SAR',
    ];
    const UNSUPPORTED_CURRENCY_MESSAGE = 'eradPay currently does not support your store currency. Please select from the list: USD, EUR, GBP, AED, SAR.';

    /**
     * Set if the place order button should be renamed on selection.
     * @var string
     */
    public $order_button_text = 'Proceed to eradPay';

    /**
     * @var mixed
     */
    private $redirect_page_id;

    /**
     * Whether to setup the hooks on calling the constructor
     * @param $hooks
     */
    public function __construct($hooks = true)
    {
        # Setup general properties
        $this->setup_properties();

        # Load the settings
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->redirect_page_id = $this->get_option('redirect_page_id');

        $this->init_hooks();
    }

    /**
     * Method for initialization in woocommerce_payment_gateways
     * Example: add_filter('woocommerce_payment_gateways', [WC_eradPay_Gateway::class, 'init']);
     *
     * @param array $methods
     * @return array
     */
    public static function init(array $methods)
    {
        $methods[] = __CLASS__;
        return $methods;
    }

    /**
     * @return void
     */
    public function init_hooks()
    {
        add_action("woocommerce_update_options_payment_gateways_{$this->id}", [$this, 'process_admin_options']);
        add_action("woocommerce_receipt_{$this->id}", [$this, 'receipt_page']);
        add_action('init', [$this, 'check_eradpay_response']);
        add_action("woocommerce_api_{$this->id}", [$this, 'check_eradpay_response']);
    }

    /**
     * Method adds links for the plugin on the plugins page
     * Example: add_filter('plugin_action_links_' . plugin_basename(__FILE__), [WC_eradPay_Gateway::class, 'add_plugin_links']);
     *
     * @param array $links
     * @return array
     */
    public static function add_plugin_links(array $links)
    {
        $settingsUrl = esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=eradpay'));
        $docsUrl = 'https://eradco.notion.site/eradPay-a16c0e3cf4a34ee2bb143af5ef06213e';
        $supportUrl = 'mailto:support@erad.co';
        $pluginLinks = [
            'settings' => "<a href='$settingsUrl'>Settings</a>",
            'docs' => "<a href='$docsUrl' target='_blank'>Docs</a>",
            'support' => "<a href='$supportUrl'>Support</a>",
        ];

        return array_merge($links, $pluginLinks);
    }

    /**
     * @return void
     */
    public function admin_options()
    {
        $is_valid_for_use = in_array(
            get_woocommerce_currency(),
            apply_filters('woocommerce_paypal_supported_currencies', self::SUPPORTED_CURRENCIES_LIST),
            true
        );
        if ($is_valid_for_use) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e('Gateway disabled', 'woocommerce'); ?></strong>:
                    <?php esc_html_e(self::UNSUPPORTED_CURRENCY_MESSAGE, 'woocommerce'); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', $this->id),
                'type' => 'checkbox',
                'label' => __('Enable this module?', $this->id),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('Title', $this->id),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', $this->id),
                'default' => __('Credit Card/Debit Card', $this->id)
            ],
            'description' => [
                'title' => __('Description', $this->id),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', $this->id),
                'default' => __('Pay securely by Credit or Debit card through Eradpay.', $this->id)
            ],
            'token' => [
                'title' => __('Api token', $this->id),
                'type' => 'text',
            ],
            'sandbox_mode' => [
                'title' => __('Sandbox Mode'),
                'type' => 'checkbox',
                'label' => __('Enable eradPay gateway sandbox mode.'),
                'default' => 'no',
                'description' => __('Tick to run sandbox transaction on the eradPay gateway'),
                'desc_tip' => true,
            ],
            'redirect_page_id' => [
                'title' => __('Return Page'),
                'type' => 'select',
                'options' => $this->get_redirect_pages_list('Select Page'),
                'description' => __('URL of success page'),
                'desc_tip' => true
            ]
        ];
    }

    public function receipt_page($order_id)
    {
        $order = new WC_Order($order_id);

        $payment_args = $this->get_payment_arguments($order);

        $html = '<p>'.__('Thank you for your order, please click the button below to pay with <b>eradPay</b>.', $this->id).'</p>';
        $html .= $this->generate_order_form($payment_args);
        echo $html;
    }

    /**
     * @param $order
     * @return array
     */
    private function get_payment_arguments($order)
    {
        $order_id = $order->get_order_number();

        return [
            'token' => $this->get_option('token'),
            'name' => html_entity_decode(get_bloginfo('name'), ENT_QUOTES),
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'description' => "Order $order_id",
            'order_id' => $order->get_id(),
            'cancel_url' => wc_get_checkout_url(),
            'callback_url' => add_query_arg([
                'wc-api' => $this->id,
                'order_key' => $this->get_order_key($order),
            ], trailingslashit(get_home_url())),
            'prefill' => $this->get_customer_info($order)
        ];
    }

    /**
     * @param $order
     * @return array
     */
    private function get_customer_info($order)
    {
        if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>=')) {
            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $email = $order->get_billing_email();
            $contact = $order->get_billing_phone();
        } else {
            $name = $order->billing_first_name . ' ' . $order->billing_last_name;
            $email = $order->billing_email;
            $contact = $order->billing_phone;
        }

        return [
            'name' => $name,
            'email' => $email,
            'contact' => $contact,
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    private function enqueue_payment_scripts(array $data)
    {
        wp_register_script('eradpay_wc_script_payment', ERADPAY_PLUGIN_PATH  . 'assets/js/eradpay-payment.js',null, null);
        wp_localize_script('eradpay_wc_script_payment','eradpay_wc_payment_vars', $data);
        wp_enqueue_script('eradpay_wc_script_payment');

        wp_register_style('eradpay_wc_style', ERADPAY_PLUGIN_PATH  . 'assets/css/eradpay-payment.css', null, null);
        wp_enqueue_style('eradpay_wc_style');
    }

    /**
     * @param array $payment_args
     * @return void
     */
    private function generate_order_form(array $payment_args)
    {
        $this->enqueue_payment_scripts($payment_args);

        $closeIconSrc = ERADPAY_PLUGIN_PATH  . 'assets/img/close_icon.svg';
        return <<<EOT
            <div class="js-eradpay-modal-fader eradpay-modal-fader"></div>
            <div id="eradpay-modal" class="eradpay-modal-window js-eradpay-modal-window">
                <img src="$closeIconSrc" class="js-eradpay-modal-close-control eradpay-modal-window__close-control" />
                <div class="js-eradpay-modal-window-content eradpay-modal-window__content">
                    <!-- content here -->
                </div>
            </div>
            <p>
                <button class="eradpay-btn eradpay-btn__submit js-eradpay-submit" data-target="eradpay-modal">Pay Now</button>
                <button class="eradpay-btn eradpay-btn__cancel js-eradpay-cancel">Cancel</button>
            </p>     
EOT;
    }

    /**
     * @return void
     */
    public function setup_properties()
    {
        $this->id = 'eradpay';
        $this->method_title = __('eradPay', $this->id);
        $this->method_description = __('Pay via eradPay; you can pay securely with your debit or credit card.', $this->id);
        $this->icon = apply_filters('woocommerce_eradpay_icon', ERADPAY_PLUGIN_PATH . 'assets/img/logo.svg');
        $this->has_fields = false;
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available() {
        if (! in_array(get_woocommerce_currency(), self::SUPPORTED_CURRENCIES_LIST)) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Process the payment and return the result.
     *
     * @param $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order_key = $this->get_order_key($order);
        $order_id = $order->get_id();
        $checkout_payment_url = $order->get_checkout_payment_url(true);

        if ($order->get_total() > 0) {
            // Mark as processing
            $order->update_status('pending');
        } else {
            // We don't need the payment process if there is no total
            $order->payment_complete();
        }

        return [
            'result' => 'success',
            'redirect' => $this->get_process_payment_redirect_url($order_id, $order_key, $checkout_payment_url),
        ];
    }

    /**
     * Check for eradPay server callback
     */
    public function check_eradpay_response()
    {
        $order_key = sanitize_text_field($_GET['order_key']);
        $order_id = wc_get_order_id_by_order_key($order_key);
        $order = wc_get_order($order_id);

        // If the order has already been paid for redirect user to success page
        if (! $order->needs_payment()) {
            $this->redirect_user($order);
        }

        // @TODO fix this dirty hack
        $transaction_post_json = file_get_contents('php://input');
        try {
            $transaction_post = json_decode($transaction_post_json, true);
        } catch (Exception $e) {
            $transaction_post = [];
        }
        $transaction_post_data = ! empty($transaction_post['data']) ? $transaction_post['data']: [];
        $transaction_id = ! empty($transaction_post_data) ? $transaction_post_data['transaction']['id']: null;
        $transaction_payment_id = ! empty($transaction_post_data) ? $transaction_post_data['transaction']['custom_fields']['payment_id']: null;
        $transaction_successful = $transaction_post && $transaction_post['type'] == 'transaction.successful';

        $transaction_id = sanitize_text_field($transaction_id);
        $transaction_payment_id = sanitize_text_field($transaction_payment_id);
        $transaction_successful = sanitize_text_field($transaction_successful);

        $success = false;
        if (
            $transaction_id &&
            $order_id == $transaction_payment_id &&
            $transaction_successful
        ) {
            $success = true;
        }

        $this->update_order($order, $success, $transaction_id);
        $this->redirect_user($order);
    }

    /**
     * Modifies existing order and handles success case
     *
     * @param $success, & $order
     */
    public function update_order(& $order, $success, $transaction_id, $error = '')
    {
        global $woocommerce;

        $order_id = $order->get_order_number();

        if ($success && $order->needs_payment() === true) {
            $msg = "Success!" . "&nbsp; Order Id: $order_id";
            $class = 'success';

            $order->update_meta_data('eradpay_transaction_id', $transaction_id);
            $order->payment_complete($transaction_id);
            $order->add_order_note("eradPay payment successful <br/>eradPay transaction_id: $transaction_id");

            if (isset($woocommerce->cart) === true) {
                $woocommerce->cart->empty_cart();
            }
        } else {
            $msg = $error;
            $class = 'error';
            $order->add_order_note("Transaction Failed: $error<br/>");
            $order->update_status('failed');
        }

        $this->add_notice($msg, $class);
    }

    /**
     * Add a woocommerce notification message
     *
     * @param string $message Notification message
     * @param string $type Notification type, default = notice
     */
    private function add_notice($message, $type = 'notice')
    {
        global $woocommerce;

        $type = in_array($type, ['notice','error','success'], true) ? $type : 'notice';
        wc_add_notice($message, $type);
    }

    private function redirect_user($order)
    {
        $redirect_url = $this->get_return_url($order);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * @param $title
     * @return array
     */
    private function get_redirect_pages_list($title = false)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = [];
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            $has_parent = $page->post_parent;
            while ($has_parent) {
                $prefix .= ' - ';
                $next_page = get_post($has_parent);
                $has_parent = $next_page->post_parent;
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }

    /**
     * Gets the order Key from the order
     */
    private function get_order_key($order)
    {
        if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>=')) {
            return $order->get_order_key();
        }

        return $order->order_key;
    }

    /**
     * @param $order_id
     * @param $order_key
     * @param $checkout_payment_url
     * @return string
     */
    private function get_process_payment_redirect_url($order_id, $order_key, $checkout_payment_url)
    {
        if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
            $redirect_url = add_query_arg(
                'key',
                $order_key,
                $checkout_payment_url
            );
        } else if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $redirect_url = add_query_arg(
                'order',
                $order_id,
                add_query_arg('key', $order_key, $checkout_payment_url)
            );
        } else {
            $redirect_url = add_query_arg(
                'order',
                $order_id,
                add_query_arg('key', $order_key, get_permalink(get_option('woocommerce_pay_page_id')))
            );
        }

        return $redirect_url;
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     * emergency|alert|critical|error|warning|notice|info|debug
     */
    private static function log( $message, $level = 'info' )
    {
        static $logger = null;
        if (empty($logger)) {
            $logger = wc_get_logger();
        }
        $logger->log( $level, $message, ['source' => 'eradpay']);
    }
}
