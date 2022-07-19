<?php
/**
 * WooCommerce eradPay Api
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_eradPay_Api
{
    const API_URL = 'https://api.erad.co/eradpay';
    const API_EMBED_URL = 'https://app.erad.co/eradpay';

    const ENDPOINT_FETCH_PAYMENT_INFO = '/fetch-payment-info';

    /**
     * Get the response from an API request.
     * @param string $endpoint
     * @param array $params
     * @param string $method
     * @return array
     */
    public static function send_request($method = 'GET', $endpoint = '', $params = [])
    {
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
        ];

        $api_url = self::API_URL . $endpoint;
        if (in_array($method, ['POST'])) {
            $args['body'] = json_encode($params);
        } else {
            $query_params = http_build_query($params);
            $api_url .= "?{$query_params}";
        }

        $response = wp_remote_request(esc_url_raw($api_url), $args);
        if (is_wp_error($response)) {
            return [false, $response->get_error_message()];
        } else {
            $result = json_decode($response['body'], true);
            $code = $response['response']['code'];
            if (in_array($code, [200], true)) {
                return [true, $result];
            } else {
                return [false, $code];
            }
        }
    }

    /**
     * @param $token
     * @param $order_id
     * @param $retries
     * @return array
     */
    public static function fetch_payment_data_with_retries($token, $order_id, $retries = 5)
    {
        $retries_total = $retries;
        $status = false;
        $data = [];
        $success = false;
        while (! $success && $retries > 0 ) {
            list($status, $data) = self::fetch_payment_data($token, $order_id);
            $success = ! empty($data);
            if ($success) {
                break;
            }

            sleep($retries_total / $retries + 3);
            $retries--;
        }

        return [$status, $data];
    }

    private static function fetch_payment_data($token, $order_id)
    {
        return self::send_request('GET', WC_eradPay_Api::ENDPOINT_FETCH_PAYMENT_INFO, [
            'token' => $token,
            'order_id' => $order_id,
        ]);
    }

    /**
     * Create a new charge request.
     * @param int $amount
     * @param string $currency
     * @param array $metadata
     * @param string $redirect
     * @param string $name
     * @param string $desc
     * @param string $cancel
     * @return array
     */
    public static function create_charge($amount = null, $currency = null, $environment = 'sandbox')
    {
        $args = array(
            'environment' => $environment
        );

        if (is_null($amount)) {
            return array(false, 'Missing amount');
        }
        $args['amount'] = floatval($amount);

        if (is_null($currency)) {
            return array(false, 'Missing currency');
        }
        $args['currency'] = $currency;

        $client = '';
        if ($environment === self::$SANDBOX) {
            $client = self::$sandbox_api_key;
        } else if ($environment === self::$PRODUCTION) {
            $client = self::$production_api_key;
        } else {
            return array(false, 'Invalid environment');
        }

        if ($client === '') {
            return array(false, 'Missing client');
        }
        $args['client'] = $client;

        $result = self::send_request($environment, self::$init_transaction_endpoint, $args, 'POST');

        return $result;
    }
}
