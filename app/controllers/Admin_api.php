<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Admin_api extends Docuquest_api
{
    public function __construct()
    {
        parent::__construct();
        $this->docuquest = $this->call->model('Admin_model');
    }

    public function adminHomepageSettings()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
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

    public function adminAccounts()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $this->api->respond(['accounts' => $this->docuquest->listAdminAccounts()]);
    }

    public function createAdminAccount()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $username = trim($this->stringValue($input, 'username'));
        $email = strtolower(trim($this->stringValue($input, 'email')));
        $password = $this->stringValue($input, 'password');
        $confirmation = $this->stringValue($input, 'password_confirmation');

        if (strlen($username) < 2 || strlen($username) > 100) {
            $this->api->respond_error('Name must be between 2 and 100 characters.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->api->respond_error('Enter a valid email address.', 422);
        }
        if (strlen($password) < 10) {
            $this->api->respond_error('Password must be at least 10 characters long.', 422);
        }
        if ($password !== $confirmation) {
            $this->api->respond_error('Password confirmation does not match.', 422);
        }
        if ($this->docuquest->findUserByEmail($email)) {
            $this->api->respond_error('An account with this email already exists.', 409);
        }
        if ($this->docuquest->findUserByUsername($username)) {
            $this->api->respond_error('An account with this name already exists. Please use another name.', 409);
        }

        $accountId = $this->docuquest->createAdminUser($username, $email, password_hash($password, PASSWORD_DEFAULT));
        $this->api->respond([
            'message' => 'Administrator account created successfully.',
            'account' => $this->docuquest->findUserById($accountId),
        ], 201);
    }

    public function updateHomepageSettings()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $contentJson = $this->stringValue($input, 'content');
        $content = json_decode($contentJson, true);
        if (!is_array($content) || strlen($contentJson) > 40000) {
            $this->api->respond_error('Homepage content is invalid or too large.', 422);
        }

        $clearImages = json_decode($this->stringValue($input, 'clear_images'), true);
        $clearImages = is_array($clearImages) ? $clearImages : [];
        $uploads = $this->validateHomepageImageUploads();
        $storedImages = [];
        foreach ($uploads as $slot => $upload) {
            $storedName = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
            if (!$this->documentStorage->put($upload['tmp_name'], $storedName, $upload['mime_type'])) {
                $this->removeUploadedFiles(array_values($storedImages));
                $this->api->respond_error('A homepage image could not be stored.', 500);
            }
            $storedImages[$slot] = ['stored_name' => $storedName, 'mime_type' => $upload['mime_type']];
        }

        try {
            $oldSettings = $this->docuquest->saveHomepageSettings(
                $content,
                $storedImages,
                [
                    'hero_background' => !empty($clearImages['hero_background']) && !isset($storedImages['hero_background']),
                    'hero_seal' => !empty($clearImages['hero_seal']) && !isset($storedImages['hero_seal']),
                ],
                (int) $user['id']
            );
        } catch (Throwable $exception) {
            $this->removeUploadedFiles(array_values($storedImages));
            throw $exception;
        }

        if ($oldSettings) {
            foreach (['hero_background', 'hero_seal'] as $slot) {
                $nameKey = $slot . '_stored_name';
                $wasCleared = !empty($clearImages[$slot]) && !isset($storedImages[$slot]);
                if (($wasCleared || isset($storedImages[$slot])) && !empty($oldSettings[$nameKey])) {
                    $this->documentStorage->delete($oldSettings[$nameKey]);
                }
            }
        }

        $this->api->respond(['message' => 'Homepage settings saved.']);
    }

    public function adminFeedback()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $feedback = $this->docuquest->getAllRequestFeedback();
        $questionKeys = [
            'staff_service_rating',
            'instructions_clarity_rating',
            'status_updates_rating',
            'processing_time_rating',
            'overall_satisfaction_rating',
        ];
        $questionSummary = [];
        foreach ($questionKeys as $key) {
            $questionSummary[$key] = ['count' => 0, 'average' => 0, 'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
        }
        foreach ($feedback as $entry) {
            foreach ($questionKeys as $key) {
                $questionRating = (int) ($entry[$key] ?? 0);
                if (isset($questionSummary[$key]['distribution'][$questionRating])) {
                    $questionSummary[$key]['distribution'][$questionRating]++;
                    $questionSummary[$key]['count']++;
                    $questionSummary[$key]['total'] = ($questionSummary[$key]['total'] ?? 0) + $questionRating;
                }
            }
        }
        foreach ($questionSummary as &$question) {
            $question['average'] = $question['count'] > 0 ? round($question['total'] / $question['count'], 2) : 0;
            unset($question['total']);
        }
        unset($question);

        $this->api->respond([
            'feedback' => $feedback,
            'question_summary' => $questionSummary,
        ]);
    }

    public function saveReport()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $start = trim((string) ($input['start'] ?? ''));
        $end = trim((string) ($input['end'] ?? ''));
        if (!$this->docuquest->isManilaDate($start) || !$this->docuquest->isManilaDate($end) || $start > $end || $end > $this->docuquest->manilaToday()) {
            $this->api->respond_error('Choose a valid report period.', 422);
        }
        $requests = $this->docuquest->getReportRequests($start, $end);
        $reportNumber = $this->docuquest->saveRegistryReport($start, $end, $requests, (int) $user['id']);
        $this->api->respond(['message' => 'Report saved to the database.', 'report_number' => $reportNumber]);
    }

    public function savedReports()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $this->api->respond(['reports' => $this->docuquest->listSavedRegistryReports()]);
    }

    public function showSavedReport()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $report = $this->docuquest->getSavedRegistryReport($this->queryId());
        if (!$report) $this->api->respond_error('Saved report not found.', 404);
        $this->api->respond(['report' => $report]);
    }

    public function adminReports()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $query = $this->api->get_query_params();
        $start = isset($query['start']) && is_string($query['start']) ? trim($query['start']) : '';
        $end = isset($query['end']) && is_string($query['end']) ? trim($query['end']) : '';
        $start = $start ?: date('Y-m-01', strtotime($this->docuquest->manilaToday()));
        $end = $end ?: $this->docuquest->manilaToday();
        if (!$this->docuquest->isManilaDate($start) || !$this->docuquest->isManilaDate($end) || $start > $end) {
            $this->api->respond_error('Choose a valid start and end date.', 422);
        }
        if ($end > $this->docuquest->manilaToday()) {
            $this->api->respond_error('Future report dates are not allowed.', 422);
        }
        $this->api->respond([
            'requests' => $this->docuquest->getReportRequests($start, $end),
            'document_types' => $this->docuquest->documentTypes(),
            'current_date' => $this->docuquest->manilaToday(),
        ]);
    }

    public function adminArchive()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $query = $this->api->get_query_params();
        $search = isset($query['q']) && is_string($query['q']) ? substr(trim($query['q']), 0, 150) : '';
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : '';
        $documentType = isset($query['document_type']) && is_string($query['document_type']) ? $query['document_type'] : '';
        $date = isset($query['date']) && is_string($query['date']) ? trim($query['date']) : '';
        if ($date !== '' && !$this->docuquest->isPastManilaDate($date)) {
            $this->api->respond_error('Choose a valid date before today.', 422);
        }

        $this->api->respond([
            'requests' => $this->docuquest->searchArchivedRequests($search, $status, $documentType, $date),
            'statuses' => $this->docuquest->statuses(),
            'document_types' => $this->docuquest->documentTypes(),
            'current_date' => $this->docuquest->manilaToday(),
        ]);
    }

    public function deleteArchivedRequest()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        if ($requestId === false || $requestId === null || $requestId < 1) {
            $this->api->respond_error('Choose a valid previous request.', 422);
        }

        $files = $this->docuquest->getRequestFiles((int) $requestId);
        if (!$this->docuquest->deleteArchivedRequest((int) $requestId)) {
            $this->api->respond_error('Previous request not found.', 404);
        }
        foreach ($files as $file) {
            if (!$this->documentStorage->delete($file['stored_name'])) {
                error_log('DocuQuest could not delete request file ' . $file['stored_name']);
            }
        }

        $this->api->respond(['message' => 'Previous request and its related database records were deleted.']);
    }

    public function adminFiles()
    {
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $query = $this->api->get_query_params();
        $search = isset($query['q']) && is_string($query['q']) ? substr(trim($query['q']), 0, 150) : '';
        $this->api->respond(['files' => $this->docuquest->getAdminFiles($search)]);
    }

    public function adminStoragePhotos()
    {
        $this->api->require_method('GET');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $query = $this->api->get_query_params();
        $token = isset($query['token']) && is_string($query['token']) ? $query['token'] : '';
        $search = isset($query['q']) && is_string($query['q']) ? trim(substr($query['q'], 0, 100)) : '';
        $date = isset($query['date']) && is_string($query['date']) ? $query['date'] : '';
        $searchDate = null;
        if ($date !== '') {
            $searchDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Manila'));
        }
        if (strlen($token) > 4096 || ($date !== '' && (!$searchDate || $searchDate->format('Y-m-d') !== $date))) {
            $this->api->respond_error('The storage page token is invalid.', 422);
        }

        $photos = [];
        $nextToken = null;
        $pageToken = $token === '' ? null : $token;
        $scanLimit = $search !== '' || $searchDate ? 10 : 1;
        try {
            for ($page = 0; $page < $scanLimit; $page++) {
                $result = $this->documentStorage->listImages($pageToken);
                $metadata = $this->docuquest->getStoragePhotoMetadata(array_column($result['photos'], 'key'));
                foreach ($result['photos'] as $photo) {
                    $upload = $metadata[$photo['key']] ?? [];
                    $photo['original_name'] = $upload['original_name'] ?? '';
                    $photo['sender_name'] = $upload['sender_name'] ?? '';
                    $photo['sender_role'] = $upload['sender_role'] ?? '';
                    $photo['created_at'] = $upload['created_at'] ?? '';
                    $photo['request_number'] = $upload['request_number'] ?? '';
                    $photo['source_type'] = $upload['source_type'] ?? '';

                    if ($search !== '') {
                        $searchable = implode(' ', [$photo['original_name'], $photo['sender_name'], $photo['request_number'], $photo['key']]);
                        if (mb_stripos($searchable, $search, 0, 'UTF-8') === false) {
                            continue;
                        }
                    }
                    if ($searchDate) {
                        $timestamp = $photo['created_at'] !== '' ? $photo['created_at'] : $photo['last_modified'];
                        try {
                            $uploadedAt = (new DateTimeImmutable($timestamp, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'));
                        } catch (Throwable $exception) {
                            continue;
                        }
                        if ($uploadedAt->format('Y-m-d') !== $date) {
                            continue;
                        }
                    }

                    $photos[] = $photo;
                }

                $pageToken = $result['next_token'];
                $nextToken = $pageToken;
                if (!$pageToken || count($photos) >= 100 || ($search === '' && !$searchDate)) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            $this->api->respond_error('Photo storage is unavailable.', 503);
        }

        $this->api->respond(['photos' => $photos, 'next_token' => $nextToken]);
    }

    public function adminStoragePhoto()
    {
        $this->api->require_method('GET');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $query = $this->api->get_query_params();
        $storedName = isset($query['key']) && is_string($query['key']) ? $query['key'] : '';
        if (!$this->documentStorage->isImageName($storedName)) {
            $this->api->respond_error('Image not found.', 404);
        }

        try {
            $contents = $this->documentStorage->get($storedName);
        } catch (Throwable $exception) {
            $this->api->respond_error('Photo storage is unavailable.', 503);
        }
        if ($contents === null) {
            $this->api->respond_error('Image not found.', 404);
        }

        $mimeType = strtolower(pathinfo($storedName, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($contents));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        echo $contents;
        exit;
    }

}
