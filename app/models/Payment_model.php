<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Payment_model extends Request_model
{
    public function setRequestPaymentAmount($requestId, $userId, $amount, $requestNumber)
    {
        $request = $this->db->table('document_requests')
            ->select('status')
            ->where('id', (int) $requestId)
            ->limit(1)
            ->get();
        if (!$request || $request['status'] !== 'processing') {
            throw new DomainException('Set the payment amount only after the request reaches Processing.');
        }

        $payment = $this->getRequestPayment($requestId);
        if ($payment && $payment['status'] === 'paid') {
            throw new DomainException('A paid request fee cannot be changed.');
        }
        $waived = number_format((float) $amount, 2, '.', '') === '0.00';
        $amountChanged = !$payment
            || number_format((float) $payment['amount'], 2, '.', '') !== number_format((float) $amount, 2, '.', '')
            || $payment['status'] !== ($waived ? 'waived' : 'pending');

        if ($payment) {
            $this->db->table('payment')
                ->where('id', (int) $payment['id'])
                ->where('status', $payment['status'])
                ->update([
                    'amount' => $amount,
                    'status' => $waived ? 'waived' : 'pending',
                    'reference_no' => $waived ? null : $payment['reference_no'],
                    'paid_at' => null,
                ]);
        } else {
            $this->db->table('payment')->insert([
                'request_id' => (int) $requestId,
                'user_id' => (int) $userId,
                'amount' => $amount,
                'method' => 'paymongo',
                'status' => $waived ? 'waived' : 'pending',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }

        $payment = $this->getRequestPayment($requestId);
        if ($amountChanged) {
            $this->createNotification(
                (int) $userId,
                (int) $requestId,
                $waived ? 'Payment not required' : 'Payment due',
                $waived
                    ? $requestNumber . ' has no fee. No payment is required.'
                    : $requestNumber . ' has a payment of PHP ' . number_format((float) $amount, 2) . ' due. Open your request to pay securely.'
            );
        }

        return $payment;
    }

    public function updatePaymentSession($paymentId, $sessionId)
    {
        return $this->db->table('payment')
            ->where('id', (int) $paymentId)
            ->where('status', 'pending')
            ->update(['reference_no' => $sessionId]) === 1;
    }

    public function getPaymentBySession($sessionId)
    {
        return $this->db->table('payment p')
            ->select('p.id, p.request_id, p.user_id, p.amount, p.reference_no, p.status, r.request_number, r.full_name, r.contact_email, r.document_type')
            ->inner_join('document_requests r', 'r.id = p.request_id')
            ->where('p.reference_no', $sessionId)
            ->limit(1)
            ->get() ?: null;
    }

    public function markPaymentPaid($sessionId, $paidAt)
    {
        $payment = $this->getPaymentBySession($sessionId);
        if (!$payment) {
            return false;
        }
        if ($payment['status'] === 'paid') {
            return true;
        }

        $updated = $this->db->table('payment')
            ->where('id', (int) $payment['id'])
            ->where('status', 'pending')
            ->update(['status' => 'paid', 'paid_at' => $paidAt]);
        if ($updated === 1) {
            $this->createNotification(
                (int) $payment['user_id'],
                (int) $payment['request_id'],
                'Payment received',
                $payment['request_number'] . ' payment has been confirmed.'
            );
            $this->notifyAdmins(
                (int) $payment['request_id'],
                'Requester payment received',
                $payment['request_number'] . ' payment from ' . $payment['full_name'] . ' has been confirmed.'
            );
            $request = [
                'id' => (int) $payment['request_id'],
                'user_id' => (int) $payment['user_id'],
                'request_number' => $payment['request_number'],
                'full_name' => $payment['full_name'],
                'contact_email' => $payment['contact_email'],
                'document_type' => $payment['document_type'] ?? '',
                'requester_email' => $payment['contact_email'],
            ];
            $this->sendRequestUpdateEmail($request, 'Payment received', 100, 'Your payment has been confirmed. The official payment receipt is attached to this email.', $this->getRequestPayment((int) $payment['request_id']));
            return true;
        }

        $latest = $this->getRequestPayment((int) $payment['request_id']);
        return $latest && $latest['status'] === 'paid';
    }

}
