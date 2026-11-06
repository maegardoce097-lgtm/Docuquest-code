<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$config['document_storage_driver'] = getenv('DOCUMENT_STORAGE_DRIVER') ?: 'local';
$config['document_storage_prefix'] = getenv('DOCUMENT_STORAGE_PREFIX') ?: '';
$config['document_storage_bucket'] = getenv('DOCUMENT_STORAGE_BUCKET') ?: '';
$config['document_storage_region'] = getenv('DOCUMENT_STORAGE_REGION') ?: 'us-east-1';
$config['document_storage_endpoint'] = getenv('DOCUMENT_STORAGE_ENDPOINT') ?: '';
$config['document_storage_path_style'] = getenv('DOCUMENT_STORAGE_PATH_STYLE') ?: '';
$config['aws_access_key_id'] = getenv('AWS_ACCESS_KEY_ID') ?: '';
$config['aws_secret_access_key'] = getenv('AWS_SECRET_ACCESS_KEY') ?: '';
$config['aws_session_token'] = getenv('AWS_SESSION_TOKEN') ?: '';