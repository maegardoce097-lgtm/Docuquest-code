<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$config['paymongo_secret_key'] = getenv('PAYMONGO_SECRET_KEY') ?: '';
$config['paymongo_webhook_secret'] = getenv('PAYMONGO_WEBHOOK_SECRET') ?: '';
$config['paymongo_payment_methods'] = array_values(array_filter(array_map(
    'trim',
    explode(',', getenv('PAYMONGO_PAYMENT_METHODS') ?: 'gcash,card,qrph')
)));