<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Request_api extends Docuquest_api
{
    public function __construct()
    {
        parent::__construct();
        $this->docuquest = $this->call->model('Request_model');
    }

    public function dashboard()
    {
        $user = $this->authenticatedUser();
        $notifications = $this->call->model('Notification_model');
        $this->api->respond([
            'user' => $user,
            'requests' => $this->docuquest->getRequests($user),
            'stats' => $this->docuquest->getDashboardStats($user),
            'daily_request_count' => $user['role'] === 'user'
                ? $this->docuquest->countUserRequestsToday((int) $user['id'])
                : null,
            'daily_request_limit' => 5,
            'statuses' => $this->docuquest->statuses(),
            'document_types' => $this->docuquest->documentTypes(),
            'barangays' => $this->docuquest->barangays(),
            'unread_count' => $notifications->countUnreadNotifications((int) $user['id']),
        ]);
    }

    public function createRequest()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'user');
        $pendingPayments = $this->docuquest->getPendingPaymentRequests((int) $user['id']);
        if ($pendingPayments) {
            $this->api->respond([
                'error' => 'You have an unpaid pending payment. Please pay it before submitting another request.',
                'pending_payments' => $pendingPayments,
                'status' => 409,
            ], 409);
        }
        if ($this->docuquest->countUserRequestsToday((int) $user['id']) >= 5) {
            $this->api->respond_error('Ubos na ang limang request mo para ngayong araw. Maaari kang mag-request ulit bukas.', 429);
        }

        $input = $this->api->body();
        $fullName = trim($this->stringValue($input, 'full_name'));
        $phone = $this->docuquest->normalizePhilippineMobile(trim($this->stringValue($input, 'phone')));
        $barangay = trim($this->stringValue($input, 'address'));
        $sitio = trim($this->stringValue($input, 'sitio'));
        $documentType = trim($this->stringValue($input, 'document_type'));
        $purpose = trim($this->stringValue($input, 'purpose'));
        $privacyConsent = $this->stringValue($input, 'privacy_consent');
        if (strlen($fullName) < 2 || strlen($fullName) > 150
            || $phone === null
            || !in_array($barangay, $this->docuquest->barangays(), true)
            || strlen($sitio) > 100
            || !$this->docuquest->isValidDocumentType($documentType)
            || strlen($purpose) < 10 || strlen($purpose) > 2000
            || $privacyConsent !== '1') {
            $this->api->respond_error('Check your request details and accept the data privacy consent before submitting.', 422);
        }
        if ($this->docuquest->hasActiveRequestForDocument((int) $user['id'], $documentType)) {
            $this->api->respond_error('This document has already been requested and cannot be processed again while the existing request is active.', 409);
        }

        $address = $sitio === '' ? $barangay : $barangay . ', Sitio ' . $sitio;

        $files = $this->storeUploadedFiles();
        try {
            $requestId = $this->docuquest->createRequest(
                (int) $user['id'],
                $fullName,
                (string) $user['email'],
                $phone,
                $address,
                $documentType,
                $purpose,
                $files
            );
        } catch (Throwable $exception) {
            $this->removeUploadedFiles($files);
            throw $exception;
        }
        $this->api->respond(['message' => 'Your document request was submitted.', 'id' => $requestId], 201);
    }

    public function showRequest()
    {
        $user = $this->authenticatedUser();
        $requestId = $this->queryId();
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        if (!$request) {
            $this->api->respond_error('Request not found.', 404);
        }

        $payment = $this->docuquest->getRequestPayment($requestId);
        if ($payment) {
            unset($payment['reference_no'], $payment['user_id']);
        }

        $this->api->respond([
            'request' => $request,
            'statuses' => $this->docuquest->statuses(),
            'updates' => $this->docuquest->getRequestUpdates($requestId),
            'messages' => $this->docuquest->getMessages($requestId, $user['role'] === 'admin'),
            'requirement_verifications' => $this->docuquest->getRequirementVerifications($requestId, $request['requirements'] ?? ''),
            'requirement_uploads' => $this->docuquest->getRequestRequirementUploads($requestId, $request['requirements'] ?? ''),
            'files' => $user['role'] === 'admin'
                ? $this->docuquest->getRequestFiles($requestId)
                : $this->docuquest->getCertifiedCopies($requestId),
            'payment' => $payment,
            'feedback' => $user['role'] === 'user'
                ? $this->docuquest->getRequestFeedback($requestId, (int) $user['id'])
                : null,
        ]);
    }

    public function submitFeedback()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'user');
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $survey = $input['survey'] ?? null;
        $clientType = is_array($survey) ? ($survey['client_type'] ?? '') : '';
        $sex = is_array($survey) ? ($survey['sex'] ?? '') : '';
        $region = is_array($survey) ? ($survey['region'] ?? '') : '';
        $contactEmail = is_array($survey) ? ($survey['email'] ?? '') : '';
        $comment = is_array($survey) ? ($survey['suggestions'] ?? '') : '';
        $ageValue = is_array($survey) && isset($survey['age']) && $survey['age'] !== ''
            ? filter_var($survey['age'], FILTER_VALIDATE_INT)
            : null;
        $cc1 = is_array($survey) ? filter_var($survey['cc1'] ?? null, FILTER_VALIDATE_INT) : false;
        $invalidSurvey = !is_array($survey)
            || !in_array($clientType, ['citizen', 'business', 'government'], true)
            || !in_array($sex, ['', 'male', 'female'], true)
            || !is_string($region) || strlen(trim($region)) > 120
            || !is_string($contactEmail) || strlen(trim($contactEmail)) > 255
            || (trim($contactEmail) !== '' && !filter_var(trim($contactEmail), FILTER_VALIDATE_EMAIL))
            || !is_string($comment) || strlen(trim($comment)) > 1000
            || ($ageValue !== null && ($ageValue === false || $ageValue < 1 || $ageValue > 120))
            || $cc1 === false || $cc1 < 1 || $cc1 > 4;
        $cc2 = $cc1 === 4 ? null : (is_array($survey) ? filter_var($survey['cc2'] ?? null, FILTER_VALIDATE_INT) : false);
        $cc3 = $cc1 === 4 ? null : (is_array($survey) ? filter_var($survey['cc3'] ?? null, FILTER_VALIDATE_INT) : false);
        if ($cc1 !== 4 && ($cc2 === false || $cc2 === null || $cc2 < 1 || $cc2 > 5
            || $cc3 === false || $cc3 === null || $cc3 < 1 || $cc3 > 4)) {
            $invalidSurvey = true;
        }

        $sqdKeys = ['sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8'];
        $validatedSurvey = [];
        $applicableRatings = 0;
        if (is_array($survey)) {
            foreach ($sqdKeys as $key) {
                $value = filter_var($survey[$key] ?? null, FILTER_VALIDATE_INT);
                if ($value === false || $value === null || $value < 0 || $value > 5) {
                    $invalidSurvey = true;
                    break;
                }
                $validatedSurvey[$key] = (int) $value;
                $applicableRatings += $value > 0 ? 1 : 0;
            }
        }
        if ($applicableRatings < 1) {
            $invalidSurvey = true;
        }
        if ($requestId === false || $requestId === null || $requestId < 1 || $invalidSurvey) {
            $this->api->respond_error('Sagutan nang wasto ang mga kinakailangang tanong sa Citizen’s Charter at SQD.', 422);
        }

        $validatedSurvey = array_merge($validatedSurvey, [
            'client_type' => $clientType,
            'sex' => $sex !== '' ? $sex : null,
            'age' => $ageValue,
            'region' => trim($region) !== '' ? trim($region) : null,
            'cc1' => (int) $cc1,
            'cc2' => $cc2 === null ? null : (int) $cc2,
            'cc3' => $cc3 === null ? null : (int) $cc3,
            'email' => trim($contactEmail) !== '' ? trim($contactEmail) : null,
            'suggestions' => trim($comment),
        ]);

        $request = $this->docuquest->findRequestForUser((int) $requestId, $user);
        if (!$request) {
            $this->api->respond_error('Request not found.', 404);
        }
        if ($request['status'] !== 'completed') {
            $this->api->respond_error('Feedback is available only for completed requests.', 409);
        }
        if ($this->docuquest->getRequestFeedback((int) $requestId, (int) $user['id'])) {
            $this->api->respond_error('Feedback has already been submitted for this request.', 409);
        }

        $feedbackId = $this->docuquest->saveRequestFeedback(
            (int) $requestId,
            (int) $user['id'],
            $validatedSurvey
        );
        if ($feedbackId < 1) {
            $this->api->respond_error('Your feedback could not be saved.', 500);
        }
        $savedFeedback = $this->docuquest->getRequestFeedback((int) $requestId, (int) $user['id']);
        if (!$savedFeedback) {
            error_log('DocuQuest feedback was saved but could not be reloaded for request ' . (int) $requestId . '.');
            $this->api->respond_error('Your feedback was saved but could not be loaded. Please refresh the request.', 500);
        }
        $emailSent = $this->docuquest->sendFeedbackNotificationEmail($request, $savedFeedback);
        $this->api->respond([
            'message' => 'Thank you for sharing your DocuQuest experience.',
            'feedback' => $savedFeedback,
            'email_sent' => $emailSent,
        ], 201);
    }

    public function downloadFile()
    {
        $this->api->require_method('GET');
        $user = $this->authenticatedUser();
        $file = $this->docuquest->findRequestFileForUser($this->queryId(), $user);
        if (!$file || !preg_match('/\A[a-f0-9]{32}\.(pdf|jpg|png)\z/i', $file['stored_name'])) {
            $this->api->respond_error('File not found.', 404);
        }

        try {
            $contents = $this->documentStorage->get($file['stored_name']);
        } catch (Throwable $exception) {
            $this->api->respond_error('File storage is unavailable.', 503);
        }
        if ($contents === null) {
            $this->api->respond_error('File not found.', 404);
        }

        $downloadName = str_replace(["\r", "\n", '"'], '', basename($file['original_name']));
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . strlen($contents));
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('X-Content-Type-Options: nosniff');
        echo $contents;
        exit;
    }

    public function downloadMessageFile()
    {
        $this->api->require_method('GET');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $file = $this->docuquest->findMessageFileForUser($this->queryId(), $user);
        if (!$file || !preg_match('/\\A[a-f0-9]{32}\\.(jpg|png)\\z/i', $file['stored_name'])) {
            $this->api->respond_error('Image not found.', 404);
        }

        try {
            $contents = $this->documentStorage->get($file['stored_name']);
        } catch (Throwable $exception) {
            $this->api->respond_error('File storage is unavailable.', 503);
        }
        if ($contents === null) {
            $this->api->respond_error('Image not found.', 404);
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . strlen($contents));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        echo $contents;
        exit;
    }

    public function updateStatus()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $status = $this->stringValue($input, 'status');
        $note = trim($this->stringValue($input, 'note'));
        $requirements = array_key_exists('requirements', $input)
            ? (is_string($input['requirements']) ? trim($input['requirements']) : false)
            : null;
        $releaseAtInput = array_key_exists('release_at', $input)
            ? (is_string($input['release_at']) ? trim($input['release_at']) : false)
            : null;
        $request = $this->docuquest->findRequestForUser($requestId, $user);

        if (!$request || !isset($this->docuquest->statuses()[$status]) || strlen($note) > 1000
            || $requirements === false || ($requirements !== null && strlen($requirements) > 5000)
            || $releaseAtInput === false) {
            $this->api->respond_error('The request update is invalid.', 422);
        }
        if (in_array($request['status'], ['completed', 'rejected'], true)) {
            $this->api->respond_error('Closed requests cannot be updated.', 409);
        }
        if (!$this->docuquest->canTransitionRequestStatus($request['status'], $status)) {
            $this->api->respond_error('Move the request to its next progress step. Repeating or reverting to an earlier status is not allowed.', 409);
        }

        if (in_array($status, ['approved', 'ready_for_pickup', 'completed'], true)) {
            $payment = $this->docuquest->getRequestPayment($requestId);
            if (!$this->docuquest->isPaymentSatisfied($payment)) {
                $this->api->respond_error('The requester must pay through PayMongo before a certified copy can be approved, released, or completed.', 409);
            }
        }

        $releaseAt = null;
        $releaseForValidation = null;
        if ($status === 'completed') {
            $releaseAt = null;
        } elseif ($releaseAtInput !== null) {
            if ($releaseAtInput === '') {
                $releaseAt = '';
            } else {
                $manila = new DateTimeZone('Asia/Manila');
                $parsedRelease = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $releaseAtInput, $manila);
                $parseErrors = DateTimeImmutable::getLastErrors();
                if (!$parsedRelease || $parsedRelease->format('Y-m-d\\TH:i') !== $releaseAtInput
                    || ($parseErrors && ($parseErrors['warning_count'] || $parseErrors['error_count']))) {
                    $this->api->respond_error('Enter a valid release date and time.', 422);
                }
                if ($parsedRelease <= new DateTimeImmutable('now', $manila)) {
                    $this->api->respond_error('The release date and time must be in the future.', 422);
                }
                $releaseForValidation = $parsedRelease;
                $releaseAt = $parsedRelease->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        } elseif (!empty($request['release_at'])) {
            $storedRelease = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $request['release_at'], new DateTimeZone('UTC'));
            $releaseForValidation = $storedRelease ? $storedRelease->setTimezone(new DateTimeZone('Asia/Manila')) : null;
        }

        $effectiveRequirements = $requirements === null
            ? trim((string) ($request['requirements'] ?? ''))
            : trim((string) $requirements);
        if (in_array($status, ['ready_for_pickup', 'completed'], true)
            && $effectiveRequirements !== '') {
            if ($effectiveRequirements !== trim((string) ($request['requirements'] ?? ''))) {
                $this->api->respond_error('Save the new requirements first. The requester must upload each item before it can be verified.', 409);
            }
            if (!$this->docuquest->areRequirementsVerified($requestId, $effectiveRequirements)) {
                $this->api->respond_error('Require the requester to upload each listed item and verify every requirement before release or completion.', 409);
            }
        }

        if ($status === 'ready_for_pickup'
            && (!$releaseForValidation || $releaseForValidation <= new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))) {
            $this->api->respond_error('Set a future release date and time before marking this request ready for pickup.', 422);
        }

        $emailSent = $this->docuquest->updateRequestStatus(
            $request,
            $status,
            $note,
            (int) $user['id'],
            $requirements,
            $releaseAt
        );
        $this->api->respond([
            'message' => $emailSent
                ? 'Progress updated and emailed to the requester.'
                : 'Progress was saved, but the email could not be sent. Check SMTP settings and server logs.',
            'email_sent' => $emailSent,
        ]);
    }

    public function uploadRequirement()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'user');
        $input = array_merge($this->api->body(), $_POST);
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $index = $index === false || $index === null ? -1 : (int) $index;
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        if (!$request || $index < 0) {
            $this->api->respond_error('Choose a valid request requirement.', 422);
        }
        if (in_array($request['status'], ['completed', 'rejected'], true)) {
            $this->api->respond_error('Files cannot be uploaded to a closed request.', 409);
        }

        $items = $this->docuquest->requirementItems($request['requirements'] ?? '');
        if (!isset($items[$index])) {
            $this->api->respond_error('This requirement is no longer active. Refresh the request and try again.', 409);
        }

        $file = $this->storeRequirementUpload();
        try {
            $fileId = $this->docuquest->saveRequestRequirementUpload(
                $requestId,
                (int) $user['id'],
                $index,
                $request['requirements'],
                $items[$index],
                $file
            );
            if ($fileId < 1) {
                throw new RuntimeException('The requirement file record could not be saved.');
            }
        } catch (Throwable $exception) {
            $this->documentStorage->delete($file['stored_name']);
            throw $exception;
        }

        $this->api->respond(['message' => 'Requirement file uploaded for office review.'], 201);
    }

    public function verifyRequirement()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $this->requireRole($user, 'admin');
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
        $index = $index === false ? -1 : (int) $index;
        $verified = filter_var($input['verified'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        if (!$request || $index < 0 || $verified === null) {
            $this->api->respond_error('The requirement verification is invalid.', 422);
        }
        if ($verified && !$this->docuquest->hasRequirementUpload($requestId, $request['requirements'] ?? '', $index)) {
            $this->api->respond_error('The requester has not uploaded a file for this requirement yet.', 409);
        }
        $verifications = $this->docuquest->setRequirementVerified($requestId, $request['requirements'] ?? '', $index, (bool) $verified);
        $this->api->respond(['requirement_verifications' => $verifications]);
    }

    public function reviewRequirementUpload()
    {
        $this->api->require_method('POST');
        $admin = $this->authenticatedUser();
        $this->requireRole($admin, 'admin');
        $input = $this->api->body();
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $fileId = filter_var($input['file_id'] ?? null, FILTER_VALIDATE_INT);
        $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
        $reviewStatus = isset($input['review_status']) && is_string($input['review_status']) ? $input['review_status'] : '';
        if ($requestId === false || $requestId === null || $requestId < 1
            || $fileId === false || $fileId === null || $fileId < 1
            || $index === false || $index === null || $index < 0
            || !in_array($reviewStatus, ['validated', 'rejected'], true)) {
            $this->api->respond_error('Choose a valid requirement file review action.', 422);
        }

        $request = $this->docuquest->findRequestForUser((int) $requestId, $admin);
        if (!$request) {
            $this->api->respond_error('Request not found.', 404);
        }
        if (in_array($request['status'], ['completed', 'rejected'], true)) {
            $this->api->respond_error('Requirements cannot be reviewed on a closed request.', 409);
        }
        try {
            $verifications = $this->docuquest->reviewRequestRequirementUpload(
                (int) $requestId,
                (int) $fileId,
                (int) $index,
                $request['requirements'] ?? '',
                $reviewStatus,
                (int) $admin['id'],
                (int) $request['user_id'],
                (string) $request['request_number']
            );
        } catch (InvalidArgumentException $exception) {
            $this->api->respond_error($exception->getMessage(), 422);
        }
        if ($verifications === false) {
            $this->api->respond_error('This is no longer the latest file for that requirement. Refresh the request before reviewing.', 409);
        }

        $this->api->respond([
            'message' => $reviewStatus === 'validated' ? 'Requirement file validated.' : 'Requirement file rejected; the requester was notified to upload a replacement.',
            'requirement_verifications' => $verifications,
        ]);
    }

    public function statusFeed()
    {
        $user = $this->authenticatedUser();
        $requestId = $this->queryId();
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        if (!$request) {
            $this->api->respond_error('Request not found.', 404);
        }
        $status = $this->docuquest->statuses()[$request['status']];
        $this->api->respond([
            'status' => $request['status'],
            'label' => $status['label'],
            'progress' => (int) $request['progress'],
            'admin_note' => $request['admin_note'],
            'updated_at' => $request['updated_at'],
        ]);
    }

    public function messages()
    {
        $user = $this->authenticatedUser();
        $requestId = $this->queryId();
        if (!$this->docuquest->findRequestForUser($requestId, $user)) {
            $this->api->respond_error('Request not found.', 404);
        }
        $this->api->respond(['messages' => $this->docuquest->getMessages($requestId, true)]);
    }

    public function sendMessage()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $input = array_merge($this->api->body(), $_POST);
        $requestId = filter_var($input['request_id'] ?? null, FILTER_VALIDATE_INT);
        $requestId = $requestId === false ? 0 : (int) $requestId;
        $body = trim($this->stringValue($input, 'body'));
        $request = $this->docuquest->findRequestForUser($requestId, $user);
        $hasImage = isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$request || (!$body && !$hasImage) || strlen($body) > 2000) {
            $this->api->respond_error('Write a message or attach an image. Messages can be up to 2,000 characters.', 422);
        }

        $attachment = $this->storeMessageImage();
        try {
            $this->docuquest->sendMessage($request, $user, $body, $attachment);
        } catch (Throwable $exception) {
            if ($attachment) {
                $this->documentStorage->delete($attachment['stored_name']);
            }
            throw $exception;
        }
        $this->api->respond(['message' => 'Message sent.']);
    }

}
