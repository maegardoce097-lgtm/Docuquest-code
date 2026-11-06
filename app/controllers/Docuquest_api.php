<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Docuquest_api extends Controller
{
    protected $api;
    protected $docuquest;
    protected $documentStorage;
    protected $paymongo;

    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->api = $this->call->library('api');
        $this->documentStorage = $this->call->library('document_storage');
        $this->paymongo = $this->call->library('paymongo');
        $this->docuquest = $this->call->model('Docuquest_model');
    }

    protected function authenticatedUser()
    {
        $payload = $this->api->require_jwt();
        $user = $this->docuquest->findUserById((int) $payload['sub']);
        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }
        return $user;
    }

    protected function requireRole(array $user, $role)
    {
        if ($user['role'] !== $role) {
            $this->api->respond_error('Forbidden', 403);
        }
    }

    protected function stringValue(array $data, $key)
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : '';
    }

    protected function queryId()
    {
        $input = $this->api->get_query_params();
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->api->respond_error('A valid request id is required.', 422);
        }
        return (int) $id;
    }

    protected function validateHomepageImageUploads()
    {
        $validated = [];
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach (['hero_background', 'hero_seal'] as $slot) {
            if (!isset($_FILES[$slot]) || ($_FILES[$slot]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $upload = $_FILES[$slot];
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !isset($upload['tmp_name'], $upload['size'])) {
                $this->api->respond_error('A homepage image could not be uploaded.', 422);
            }
            if ((int) $upload['size'] < 1 || (int) $upload['size'] > 2 * 1024 * 1024) {
                $this->api->respond_error('Homepage images must be smaller than 2 MB.', 422);
            }

            $mimeType = $finfo->file($upload['tmp_name']);
            if (!isset($extensions[$mimeType])) {
                $this->api->respond_error('Homepage images must be JPG or PNG files.', 422);
            }

            $validated[$slot] = [
                'tmp_name' => $upload['tmp_name'],
                'mime_type' => $mimeType,
                'extension' => $extensions[$mimeType],
            ];
        }
        return $validated;
    }

    protected function storeUploadedFiles()
    {
        if (!isset($_FILES['files'])) {
            return [];
        }
        $uploads = $_FILES['files'];
        if (!isset($uploads['name']) || !is_array($uploads['name'])) {
            $this->api->respond_error('Invalid file upload.', 422);
        }
        if (count($uploads['name']) > 3) {
            $this->api->respond_error('Upload up to 3 files per request.', 422);
        }

        $allowedTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $storedFiles = [];
        $fileCount = count($uploads['name']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        for ($index = 0; $index < $fileCount; $index++) {
            $error = $uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK || !isset($uploads['tmp_name'][$index], $uploads['size'][$index])) {
                $this->removeUploadedFiles($storedFiles);
                $this->api->respond_error('A file could not be uploaded.', 422);
            }
            if ((int) $uploads['size'][$index] < 1 || (int) $uploads['size'][$index] > 2 * 1024 * 1024) {
                $this->removeUploadedFiles($storedFiles);
                $this->api->respond_error('Each file must be smaller than 2 MB.', 422);
            }

            $mimeType = $finfo->file($uploads['tmp_name'][$index]);
            if (!isset($allowedTypes[$mimeType])) {
                $this->removeUploadedFiles($storedFiles);
                $this->api->respond_error('Only PDF, JPG, and PNG files are accepted.', 422);
            }

            $originalName = basename(str_replace('\\', '/', (string) $uploads['name'][$index]));
            $originalName = substr(preg_replace('/[\x00-\x1F\x7F]/', '', $originalName), 0, 255);
            $storedName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
            if (!$this->documentStorage->put($uploads['tmp_name'][$index], $storedName, $mimeType)) {
                $this->removeUploadedFiles($storedFiles);
                $this->api->respond_error('A file could not be stored.', 500);
            }
            $storedFiles[] = [
                'original_name' => $originalName ?: 'document.' . $allowedTypes[$mimeType],
                'stored_name' => $storedName,
                'mime_type' => $mimeType,
                'size_bytes' => (int) $uploads['size'][$index],
            ];
        }
        return $storedFiles;
    }

    protected function storeRequirementUpload()
    {
        $upload = $_FILES['file'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($upload['tmp_name'], $upload['size'], $upload['name'])) {
            $this->api->respond_error('Choose a requirement file to upload.', 422);
        }
        if ((int) $upload['size'] < 1 || (int) $upload['size'] > 2 * 1024 * 1024) {
            $this->api->respond_error('The requirement file must be smaller than 2 MB.', 422);
        }

        $allowedTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!isset($allowedTypes[$mimeType])) {
            $this->api->respond_error('Only PDF, JPG, and PNG requirement files are accepted.', 422);
        }

        $originalName = basename(str_replace('\\', '/', (string) $upload['name']));
        $originalName = substr(preg_replace('/[\x00-\x1F\x7F]/', '', $originalName), 0, 255);
        $storedName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
        if (!$this->documentStorage->put($upload['tmp_name'], $storedName, $mimeType)) {
            $this->api->respond_error('The requirement file could not be stored.', 500);
        }

        return [
            'original_name' => $originalName ?: 'requirement.' . $allowedTypes[$mimeType],
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $upload['size'],
        ];
    }

    protected function storeMessageImage()
    {
        if (!isset($_FILES['image']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $upload = $_FILES['image'];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($upload['tmp_name'], $upload['size'], $upload['name'])) {
            $this->api->respond_error('The image could not be uploaded.', 422);
        }
        if ((int) $upload['size'] < 1 || (int) $upload['size'] > 2 * 1024 * 1024) {
            $this->api->respond_error('Images must be smaller than 2 MB.', 422);
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mimeType])) {
            $this->api->respond_error('Only JPG and PNG images are accepted.', 422);
        }

        $originalName = basename(str_replace('\\', '/', (string) $upload['name']));
        $originalName = substr(preg_replace('/[\\x00-\\x1F\\x7F]/', '', $originalName), 0, 255);
        $storedName = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        if (!$this->documentStorage->put($upload['tmp_name'], $storedName, $mimeType)) {
            $this->api->respond_error('The image could not be stored.', 500);
        }

        return [
            'original_name' => $originalName ?: 'image.' . $extensions[$mimeType],
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $upload['size'],
        ];
    }

    protected function removeUploadedFiles(array $files)
    {
        foreach ($files as $file) {
            if (isset($file['stored_name'])) {
                $this->documentStorage->delete($file['stored_name']);
            }
        }
    }
}