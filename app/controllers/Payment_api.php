<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Payment_api extends Docuquest_api
{
    public function __construct()
    {
        parent::__construct();
        $this->docuquest = $this->call->model('Payment_model');
    }

    public function setRequestPaymentAmount()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $amount = $this->normalizePaymentAmount($input['amount'] ?? null);
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        if (!$request || $amount === null) {
            $this->api->respond_error('Choose a valid request and enter an amount of PHP 0.00 or more.', 422);
        }
        if (in_array($request['status'], ['completed', 'rejected'], true)) {
            $this->api->respond_error('A payment fee cannot be assigned to a closed request.', 409);
        }
        if ($request['status'] !== 'processing') {
            $this->api->respond_error('Set the payment amount only after the request reaches Processing.', 409);
        }

        $existing = $this->docuquest->getRequestPayment($requestId);
        if ($existing && $existing['status'] === 'paid') {
            $this->api->respond_error('The payment has already been received, so its amount cannot be changed.', 409);
        }

        $amountChanged = !$existing || $this->normalizePaymentAmount($existing['amount']) !== $amount;
        if ($amountChanged && $existing && !empty($existing['reference_no'])) {
            try {
                $session = $this->paymongo->getCheckoutSession($existing['reference_no']);
                $attributes = $session['attributes'] ?? [];
                if ($this->checkoutSessionHasPaidPayment($attributes, $this->paymentAmountInCentavos($existing['amount']))) {
                    $this->docuquest->markPaymentPaid($existing['reference_no'], gmdate('Y-m-d H:i:s'));
                    $this->api->respond_error('The requester has already paid this amount.', 409);
                }
                if (($attributes['status'] ?? '') === 'active') {
                    $this->paymongo->expireCheckoutSession($existing['reference_no']);
                }
            } catch (Throwable $exception) {
                $this->api->respond_error('The current PayMongo checkout could not be safely replaced. Ask the requester to finish or close the existing payment attempt first.', 409);
            }
        }

        try {
            $payment = $this->docuquest->setRequestPaymentAmount(
                $requestId,
                (int) $request['user_id'],
                $amount,
                $request['request_number']
            );
        } catch (DomainException $exception) {
            $this->api->respond_error($exception->getMessage(), 409);
        }
        $emailSent = $amountChanged ? $this->docuquest->sendPaymentDueEmail($request, $payment) : null;
        unset($payment['reference_no'], $payment['user_id']);

        $this->api->respond([
            'message' => $emailSent === false
                ? 'Payment amount saved, but the requester email could not be sent. Check SMTP settings.'
                : ($payment['status'] === 'waived'
                    ? 'No payment is required. The requester was notified.'
                    : 'Payment amount saved. The requester can pay from their request page.'),
            'email_sent' => $emailSent,
            'payment' => $payment,
        ]);
    }

    public function createPaymentCheckout()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        $payment = $request ? $this->docuquest->getRequestPayment($requestId) : null;
        if (!$request || !$payment) {
            $this->api->respond_error('No payment amount has been set for this request.', 404);
        }
        if ($payment['status'] === 'paid') {
            $this->api->respond(['payment' => $this->publicPayment($payment), 'message' => 'Payment is already confirmed.']);
        }
        if ($this->docuquest->isPaymentSatisfied($payment)) {
            $this->api->respond(['payment' => $this->publicPayment($payment), 'message' => 'No payment is required.']);
        }

        $amount = $this->normalizePaymentAmount($payment['amount']);
        if (!$amount || $this->paymentAmountInCentavos($amount) < 100) {
            $this->api->respond_error('The payment amount is invalid.', 409);
        }

        if (!empty($payment['reference_no'])) {
            try {
                $session = $this->paymongo->getCheckoutSession($payment['reference_no']);
            } catch (Throwable $exception) {
                $this->api->respond_error('The payment service is unavailable. Please try again later.', 503);
            }
            $attributes = $session['attributes'] ?? [];
            if ($this->checkoutSessionHasPaidPayment($attributes, $this->paymentAmountInCentavos($amount))) {
                $this->docuquest->markPaymentPaid(
                    $payment['reference_no'],
                    $this->paidAtFromCheckout($attributes)
                );
                $payment = $this->docuquest->getRequestPayment($requestId);
                $this->api->respond(['payment' => $this->publicPayment($payment), 'message' => 'Payment is already confirmed.']);
            }
            if (($attributes['payment_intent']['attributes']['status'] ?? '') === 'processing') {
                $this->api->respond_error('A payment attempt is still processing with PayMongo. Check its status before trying again.', 409);
            }
            if (($attributes['status'] ?? '') === 'active' && !empty($attributes['checkout_url'])) {
                $this->api->respond(['checkout_url' => $attributes['checkout_url']]);
            }
        }

        $baseUrl = rtrim((string) (getenv('FRONTEND_BASE_URL') ?: 'https://docuquest-design.onrender.com'), '/');
        $returnPath = '/?payment=success&request_id=' . (int) $request['id'] . '#requests';
        $paymentMethods = config_item('paymongo_payment_methods');
        if (!is_array($paymentMethods) || !$paymentMethods) {
            $this->api->respond_error('No PayMongo payment methods are configured.', 503);
        }

        try {
            $session = $this->paymongo->createCheckoutSession([
                'line_items' => [[
                    'name' => 'Document request fee',
                    'amount' => $this->paymentAmountInCentavos($amount),
                    'currency' => 'PHP',
                    'quantity' => 1,
                    'description' => $request['request_number'],
                ]],
                'payment_method_types' => $paymentMethods,
                'success_url' => $baseUrl . $returnPath,
                'cancel_url' => $baseUrl . '/?payment=cancelled&request_id=' . (int) $request['id'] . '#requests',
                'reference_number' => $request['request_number'],
                'customer_email' => $request['contact_email'],
                'billing' => [
                    'name' => $request['full_name'],
                    'email' => $request['contact_email'],
                    'phone' => $request['phone'],
                ],
                'description' => 'DocuQuest request ' . $request['request_number'],
                'send_email_receipt' => true,
                'pass_on_fees' => false,
                'metadata' => [
                    'request_id' => (string) $request['id'],
                    'payment_id' => (string) $payment['id'],
                ],
            ]);
        } catch (Throwable $exception) {
            $this->api->respond_error('Could not start PayMongo checkout. Please try again later.', 503);
        }

        $sessionId = $session['id'] ?? '';
        $checkoutUrl = $session['attributes']['checkout_url'] ?? '';
        if (!is_string($sessionId) || !is_string($checkoutUrl)
            || preg_match('/\Acs_[A-Za-z0-9]+\z/', $sessionId) !== 1
            || !str_starts_with($checkoutUrl, 'https://checkout.paymongo.com/')) {
            $this->api->respond_error('PayMongo returned an invalid checkout session.', 502);
        }
        if (!$this->docuquest->updatePaymentSession((int) $payment['id'], $sessionId)) {
            $this->api->respond_error('Could not save the checkout session. Please try again.', 409);
        }

        $this->api->respond(['checkout_url' => $checkoutUrl]);
    }

    public function confirmPayment()
    {
        $this->api->require_method('GET');
        $user = $this->authenticatedUser();
        $query = $this->api->get_query_params();
        $requestId = filter_var($query['request_id'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        $payment = $request ? $this->docuquest->getRequestPayment($requestId) : null;
        if (!$request || !$payment) {
            $this->api->respond_error('Payment not found.', 404);
        }

        if ($payment['status'] !== 'paid' && !empty($payment['reference_no'])) {
            try {
                $session = $this->paymongo->getCheckoutSession($payment['reference_no']);
            } catch (Throwable $exception) {
                $this->api->respond_error('Payment status is not available yet. Please refresh shortly.', 503);
            }
            $attributes = $session['attributes'] ?? [];
            if (($attributes['reference_number'] ?? '') !== $request['request_number']) {
                $this->api->respond_error('The payment session does not match this request.', 409);
            }
            if ($this->checkoutSessionHasPaidPayment($attributes, $this->paymentAmountInCentavos($payment['amount']))) {
                $this->docuquest->markPaymentPaid(
                    $payment['reference_no'],
                    $this->paidAtFromCheckout($attributes)
                );
                $payment = $this->docuquest->getRequestPayment($requestId);
            }
        }

        $this->api->respond(['payment' => $this->publicPayment($payment)]);
    }

    public function paymongoWebhook()
    {
        $this->api->require_method('POST');
        $rawBody = file_get_contents('php://input');
        $payload = is_string($rawBody) ? json_decode($rawBody, true) : null;
        $eventAttributes = $payload['data']['attributes'] ?? [];
        $secret = (string) config_item('paymongo_webhook_secret');
        $isLive = ($eventAttributes['livemode'] ?? false) === true;
        $apiKey = (string) config_item('paymongo_secret_key');

        if (!is_array($payload) || $rawBody === '' || $secret === ''
            || ($isLive && !str_starts_with($apiKey, 'sk_live_'))
            || (!$isLive && !str_starts_with($apiKey, 'sk_test_'))
            || !$this->validPaymongoSignature(
                $rawBody,
                (string) ($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? ''),
                $secret,
                $isLive
            )) {
            $this->webhookResponse(401, 'Invalid webhook signature.');
        }

        if (($eventAttributes['type'] ?? '') !== 'checkout_session.payment.paid') {
            $this->webhookResponse(200, 'Event ignored.');
        }

        $session = $eventAttributes['data'] ?? [];
        $sessionId = $session['id'] ?? '';
        $attributes = $session['attributes'] ?? [];
        $payment = is_string($sessionId) ? $this->docuquest->getPaymentBySession($sessionId) : null;
        if (!$payment) {
            $this->webhookResponse(200, 'No matching payment.');
        }
        if (($attributes['reference_number'] ?? '') !== $payment['request_number']) {
            error_log('PayMongo webhook reference did not match the DocuQuest request.');
            $this->webhookResponse(200, 'Reference mismatch.');
        }

        $expectedAmount = $this->paymentAmountInCentavos($payment['amount']);
        if (!$this->checkoutSessionHasPaidPayment($attributes, $expectedAmount)) {
            error_log('PayMongo paid event amount did not match the DocuQuest payment amount.');
            $this->webhookResponse(200, 'Payment amount mismatch.');
        }

        $this->docuquest->markPaymentPaid($sessionId, $this->paidAtFromCheckout($attributes));
        $this->webhookResponse(200, 'Payment confirmed.');
    }

    private function validPaymongoSignature($rawBody, $header, $secret, $isLive)
    {
        $parts = [];
        foreach (explode(',', $header) as $component) {
            $pair = explode('=', trim($component), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts[$isLive ? 'li' : 'te'] ?? '';
        if (!ctype_digit($timestamp) || $signature === '' || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    private function checkoutSessionHasPaidPayment(array $attributes, $expectedAmount)
    {
        foreach (($attributes['payments'] ?? []) as $payment) {
            $paymentAttributes = $payment['attributes'] ?? [];
            if (($paymentAttributes['status'] ?? '') === 'paid'
                && (int) ($paymentAttributes['amount'] ?? 0) === (int) $expectedAmount
                && strtoupper((string) ($paymentAttributes['currency'] ?? 'PHP')) === 'PHP') {
                return true;
            }
        }

        return false;
    }

    private function paidAtFromCheckout(array $attributes)
    {
        foreach (($attributes['payments'] ?? []) as $payment) {
            $paidAt = (int) ($payment['attributes']['paid_at'] ?? 0);
            if (($payment['attributes']['status'] ?? '') === 'paid' && $paidAt > 0) {
                return gmdate('Y-m-d H:i:s', $paidAt);
            }
        }

        return gmdate('Y-m-d H:i:s');
    }

    private function normalizePaymentAmount($amount)
    {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            return null;
        }

        $value = trim((string) $amount);
        if (preg_match('/\A([0-9]{1,8})(?:\.([0-9]{1,2}))?\z/', $value, $matches) !== 1) {
            return null;
        }
        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');

        return sprintf('%d.%02d', $whole, $fraction);
    }

    private function paymentAmountInCentavos($amount)
    {
        $normalized = $this->normalizePaymentAmount($amount);
        if ($normalized === null) {
            return 0;
        }

        [$whole, $fraction] = explode('.', $normalized, 2);
        return ((int) $whole * 100) + (int) $fraction;
    }

    private function publicPayment(?array $payment)
    {
        if (!$payment) {
            return null;
        }

        return [
            'amount' => $payment['amount'],
            'method' => $payment['method'],
            'status' => $payment['status'],
            'paid_at' => $payment['paid_at'],
            'created_at' => $payment['created_at'],
        ];
    }

    private function webhookResponse($status, $message)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => $message]);
        exit;
    }

}
