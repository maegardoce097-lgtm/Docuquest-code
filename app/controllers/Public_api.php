<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Public_api extends Docuquest_api
{
    public function __construct()
    {
        parent::__construct();
        $this->docuquest = $this->call->model('Admin_model');
    }

    public function homepageContent()
    {
        $settings = $this->docuquest->getHomepageSettingsRecord();
        $content = $settings ? json_decode($settings['content_json'], true) : null;
        $this->api->respond([
            'content' => is_array($content) ? $content : null,
            'images' => [
                'hero_background' => !empty($settings['hero_background_stored_name']),
                'hero_seal' => !empty($settings['hero_seal_stored_name']),
            ],
            'updated_at' => $settings['updated_at'] ?? null,
        ]);
    }

    public function homepageImage()
    {
        $query = $this->api->get_query_params();
        $slot = $this->stringValue($query, 'slot');
        $image = $this->docuquest->getHomepageImage($slot);
        if (!$image || !preg_match('/\\A[a-f0-9]{32}\\.(jpg|png)\\z/i', $image['stored_name'])) {
            $this->api->respond_error('Homepage image not found.', 404);
        }

        try {
            $contents = $this->documentStorage->get($image['stored_name']);
        } catch (Throwable $exception) {
            $this->api->respond_error('Image storage is unavailable.', 503);
        }
        if ($contents === null || !in_array($image['mime_type'], ['image/jpeg', 'image/png'], true)) {
            $this->api->respond_error('Homepage image not found.', 404);
        }

        header('Content-Type: ' . $image['mime_type']);
        header('Content-Length: ' . strlen($contents));
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $contents;
        exit;
    }

}
