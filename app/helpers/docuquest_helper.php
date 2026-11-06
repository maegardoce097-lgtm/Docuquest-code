<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

if (!function_exists('dq_escape')) {
    function dq_escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dq_redirect')) {
    function dq_redirect($uri)
    {
        redirect($uri, false, false);
        exit;
    }
}
