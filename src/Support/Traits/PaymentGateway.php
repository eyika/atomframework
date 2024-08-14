<?php

namespace Basttyy\FxDataServer\libs\Traits;

use GuzzleHttp\Client;

trait PaymentGateway
{
    private const paystackCurrencies = ['NGN', 'USD', 'GHS', 'ZAR', 'KES'];
    private const flutterwaveCurrencies = [...self::paystackCurrencies, 'EUR', 'ZMW', 'TNZ', 'RWF', 'XAF', 'XOF'];

    private const flutterwave = [
        'payment_plan' => 'payment-plans',
        'payment_plan_update' => 'payment-plans/id',
        'payment_plan_fetch' => 'payment-plans/id',
        'payment_plan_list' => 'payment-plans/id?perPage=_per_page&page=_page',
        'payment_plan_cancel' => 'payment-plans/id/cancel',
        'subscription_cancel' => 'subscriptions/id/cancel',
        'transaction_verify' => 'transactions/id/verify',
        'transaction_verify_byref' => 'transactions/verify_by_reference/id'
    ];

    private const paystack = [
        'payment_plan' => 'plan',
        'payment_plan_update' => 'payment-plans/id',
        'payment_plan_fetch' => 'payment-plans/id',
        'payment_plan_list' => 'payment-plans/id?perPage=_per_page&page=_page',
        'payment_plan_cancel' => 'payment-plans/id/cancel',
        'subscription_cancel' => 'subscription/disable',
        'transaction_init' => 'transaction/initialize',
        'transaction_verify' => 'transactions/id/verify',
        'transaction_verify_byref' => 'transactions/verify_by_reference/id'
    ];

    /**
     * Create a payment/subscription plan
     * 
     * @param float $amount
     * @param string $currency
     * @param string $name
     * @param string $interval
     * @param string $desc
     * 
     * @return false|\stdClass
     */
    protected static function createPaymentPlan($amount, $currency, $name, $interval, $desc = '')
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('POST', self::prepareUrl('payment_plan'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ],
            'body' => json_encode([
                'amount' => $amount,
                'currency' => $currency,
                'name' => $name,
                'interval' => $interval,
                'description' => $desc
            ])
        ]);

        if ($resp->getStatusCode() !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }


    /**
     * List all payment/subscription plan
     * 
     * @param int $perPage
     * @param int $page
     * 
     * @return false|\stdClass
     */
    protected static function listPaymentPlan($perPage = 50, $page = 1)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('GET', self::prepareUrl('payment_plan_list', null, [
            '_per_page' => $perPage,
            '_page' => $page,
        ]), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ]
        ]);

        if ($resp->getStatusCode() !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Get a payment plan
     * 
     * @param string|int $id
     * 
     * @return false|\stdClass
     */
    protected static function fetchPaymentPlan($id)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('GET', self::prepareUrl('payment_plan_fetch', $id), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ]
        ]);

        if ($resp->getStatusCode() !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Update a payment/subscription plan
     * 
     * @param string|int $id
     * @param string $name
     * @param float $amount
     * @param string $interval
     * @param string $desc
     * 
     * @return false|\stdClass
     */
    protected static function updatePaymentPlan($id, $name, $amount, $interval, $desc)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('PUT', self::prepareUrl('payment_plan', $id), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ],
            'body' => json_encode([
                'name' => $name,
                'interval' => $interval,
                'amount' => $amount,
                'description' => $desc
            ])
        ]);

        if ($resp->getStatusCode() !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Cancel a payment plan (Flutterwave Only)
     * 
     * @param string|int $id
     * 
     * @return false|\stdClass
     */
    protected static function cancelPaymentPlan($id)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('PUT', self::prepareUrl('payment_plan_cancel', $id), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ]
        ]);

        if ($resp->getStatusCode() !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Cancel a subscription to a payment plan
     * 
     * @param string|int $id (id or code)
     * @param string $token (Required only for paystack)
     * 
     * @return false|\stdClass
     */
    protected static function cancelSubscription($id, $token = null)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request(self::providerIs('paystack') ? 'POST' : 'PUT', self::prepareUrl('subscription_cancel', $id), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ],
            'body' => self::providerIs('paystack') ? [
                "code" => $id, 
                "token" => $token
            ] : null
        ]);

        if ($resp->getStatusCode()  !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Initialize a transaction process/session
     * 
     * @param int $amount
     * @param string $email
     * @param string|null $plan_code
     * 
     * @return false|\stdClass
     */
    protected static function initializeTransaction($email, $amount, $plan_code = null)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);
        $data = [
            'email' => $email,
            'amount' => $amount
        ];
        if ($plan_code)
            $data['plan'] = $plan_code;

        $resp = $client->request('POST', self::prepareUrl('transaction_init'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ],
            'body' => json_encode($data)
        ]);

        if ($resp->getStatusCode()  !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Verify a Transaction
     * 
     * @param int $id
     * 
     * @return false|\stdClass
     */
    protected static function verifyTransaction($id)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('GET', self::prepareUrl('transaction_verify', $id), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ]
        ]);

        if ($resp->getStatusCode()  !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    /**
     * Verify a Transaction by reference
     * 
     * @param string $tx_ref
     * 
     * @return false|\stdClass
     */
    protected static function verifyTransactionByRef($tx_ref)
    {
        $client = new Client([ 'base_uri' => env('FLWV_BASE_URL')]);

        $resp = $client->request('GET', self::prepareUrl('transaction_verify_byref', $tx_ref), [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('FLWV_SECRET_KEY')
            ]
        ]);

        if ($resp->getStatusCode()  !== 200) {
            return false;
        }

        return json_decode($resp->getBody(), false);
    }

    private static function prepareUrl(string $action, int|string $id = null, array $params = null)
    {
        $url = self::providerIs('paystack') ? static::flutterwave[$action] : static::paystack[$action];
        if ($id)
            $url = str_replace('/id', "/$id", $url);

        foreach ($params ?? [] as $key => $param) {
            $url = str_replace($key, $param, $url);
        }

        return $url;
    }

    private static function providerIs(string $provider)
    {
        env('PAYMENT_PROVIDER') == $provider;
    }

    private static function getSupportedCurrencies()
    {
        switch (env('PAYMENT_PROVIDER') ?? '') {
            case 'paystack':
                $currencies = self::paystackCurrencies;
                break;
            case 'flutterwave':
                $currencies = self::flutterwaveCurrencies;
                break;
            default:
                $currencies = [];
        }

        return $currencies;
    }
}