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

        add_action("woocommerce_update_options_payment_gateways_{$this->id}", [$this, 'process_admin_options']);
        add_action("woocommerce_receipt_{$this->id}", [$this, 'receipt_page']);
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

    public function receipt_page($order_id)
    {
        $order = new WC_Order($order_id);

        $payment_args = $this->get_payment_arguments($order);

        $html = '<p>'.__('Thank you for your order, please click the button below to pay with Razorpay.', $this->id).'</p>';
        $html .= $this->generate_order_form($payment_args);
        echo $html;
    }

    /**
     * @param $order
     * @return array
     */
    private function get_payment_arguments($order)
    {
        global $woocommerce;

        $order_id = $order->get_order_number();
        $session_key = self::ERADPAY_ORDER_ID . $order_id;
        $eradpay_order_id = get_transient($session_key);

        return [
            'token' => $this->get_option('token'),
            'name' => html_entity_decode(get_bloginfo('name'), ENT_QUOTES),
            'currency' => 'USD',
            'description' => "Order $order_id",
            'order_id' => $eradpay_order_id,
            'cancel_url' => wc_get_checkout_url(),
            'callback_url' => $this->get_redirect_url($order),
            'prefill' => $this->get_customer_info($order)
        ];
    }

    /**
     * @param $order
     * @return string
     */
    private function get_redirect_url($order)
    {
        $query = [
            'wc-api' => $this->id,
            'order_key' => $order->get_order_key(),
        ];

        return add_query_arg($query, trailingslashit(get_home_url()));
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
     * @param array $payment_args
     * @return void
     */
    private function generate_order_form(array $payment_args)
    {
        $this->enqueue_payment_scripts($payment_args);

        return <<<EOT
            <p>
                <button id="btn-eradpay-submit">Pay Now</button>
                <button id="btn-eradpay-cancel">Cancel</button>
            </p>
EOT;
    }

    /**
     * @param array $data
     * @return void
     */
    private function enqueue_payment_scripts(array $data)
    {
        wp_register_script('eradpay_wc_script', ERADPAY_PLUGIN_PATH  . 'scripts/payment.js',null, null);
        wp_localize_script('eradpay_wc_script','eradpay_wc_payment_vars', $data);
        wp_enqueue_script('eradpay_wc_script');
    }

    /**
     * @return void
     */
    public function setup_properties()
    {
        $this->id = 'eradpay';
        $this->method_title = __('eradPay', $this->id);
        $this->method_description = __('Pay via eradPay; you can pay securely with your debit or credit card.', $this->id);
        $this->icon = apply_filters('woocommerce_eradpay_icon', plugins_url('woo-eradpay') . '/assets/logo.svg');
        $this->has_fields = false;
    }

    /**
     * @param $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);
        $order_key = $this->get_order_key($order);
        set_transient(self::SESSION_KEY, $order_id, 3600);

        if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
            $redirect = add_query_arg(
                'key',
                $order_key,
                $order->get_checkout_payment_url(true)
            );
        } else if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $redirect = add_query_arg(
                'order',
                $order->get_id(),
                add_query_arg('key', $order_key, $order->get_checkout_payment_url(true))
            );
        } else {
            $redirect = add_query_arg(
                'order',
                $order->get_id(),
                add_query_arg('key', $order_key, get_permalink(get_option('woocommerce_pay_page_id')))
            );
        }

        return [
            'result' => 'success',
            'redirect' => $redirect,
        ];
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
                'description' => __('The key Id and key secret can be generated from "API Keys" section of Razorpay Dashboard. Use test or live for test or live mode.', $this->id)
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
                'options' => $this->get_redirect_pages('Select Page'),
                'description' => __('URL of success page'),
                'desc_tip' => true
            ]
        ];
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
        $docsUrl = 'https://erad.co?docs=todo';
        $supportUrl = 'https://erad.co?support=todo';
        $pluginLinks = [
            'settings' => "<a href='$settingsUrl'>Settings</a>",
            'docs' => "<a href='$docsUrl'>Docs</a>",
            'support' => "<a href='$supportUrl'>Support</a>",
        ];

        return array_merge($links, $pluginLinks);
    }

    /**
     * @param $title
     * @return array
     */
    private function get_redirect_pages($title = false)
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
}
