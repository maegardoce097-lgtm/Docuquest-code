<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Paymongo
{
    private $secretKey;

    public function __construct()
    {
        $this->secretKey = trim((string) config_item('paymongo_secret_key'));
    }

    public function createCheckoutSession(array $attributes)
    {
        $response = $this->request('POST', '/v2/checkout_sessions', [
            'data' => ['attributes' => $attributes],
        ]);
        return $response['data'] ?? null;
    }

    public function getCheckoutSession($sessionId)
    {
        $sessionId = $this->validateSessionId($sessionId);
        $response = $this->request('GET', '/v1/checkout_sessions/' . rawurlencode($sessionId));
        return $response['data'] ?? null;
    }

    public function expireCheckoutSession($sessionId)
    {
        $sessionId = $this->validateSessionId($sessionId);
        $response = $this->request('POST', '/v1/checkout_sessions/' . rawurlencode($sessionId) . '/expire', []);
        return $response['data'] ?? null;
    }

    private function request($method, $path, array $payload = null)
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('PayMongo is not configured.');
        }

        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->secretKey . ':',
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_THROW_ON_ERROR);
        }

        $handle = curl_init('https://api.paymongo.com' . $path);
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            error_log('PayMongo API request failed with HTTP ' . $status . ($curlError !== '' ? ': ' . $curlError : '.'));
            throw new RuntimeException('The payment service could not process the request. Please try again.');
        }

        $response = json_decode($body, true);
        if (!is_array($response)) {
            throw new RuntimeException('The payment service returned an invalid response.');
        }

        return $response;
    }

    private function validateSessionId($sessionId)
    {
        if (!is_string($sessionId) || preg_match('/\Acs_[A-Za-z0-9]+\z/', $sessionId) !== 1) {
            throw new InvalidArgumentException('Invalid PayMongo checkout session.');
        }

        return $sessionId;
    }
}