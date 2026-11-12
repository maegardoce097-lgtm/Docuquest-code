<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Request_model extends Docuquest_model
{
    public function getRequests(array $user)
    {
        $query = $this->db->table('document_requests r')
            ->select($this->requestListColumns() . ', u.username AS requester_name, u.email AS requester_email')
            ->inner_join('users u', 'u.id = r.user_id');
        if ($user['role'] === 'admin') {
            $today = $this->manilaDayBounds();
            $query->where('r.created_at', '>=', $today['start_utc'])
                ->where('r.created_at', '<', $today['end_utc']);
        } else {
            $query->where('r.user_id', (int) $user['id']);
        }
        $requests = $query->get_all();
        $statusOrder = array_flip(array_keys($this->statusMap));
        usort($requests, static function ($left, $right) use ($user, $statusOrder) {
            if ($user['role'] === 'admin') {
                return [$left['created_at'], (int) $left['id']] <=> [$right['created_at'], (int) $right['id']];
            }
            $statusComparison = $statusOrder[$left['status']] <=> $statusOrder[$right['status']];
            if ($statusComparison !== 0) {
                return $statusComparison;
            }
            return [$right['created_at'], (int) $right['id']] <=> [$left['created_at'], (int) $left['id']];
        });
        return $this->addManilaSubmissionTimes($requests);
    }

    public function searchRequests($search, $status = '', $documentType = '')
    {
        return $this->queryRequestSearch($search, $status, $documentType);
    }

    public function searchArchivedRequests($search, $status = '', $documentType = '', $date = '')
    {
        $today = $this->manilaDayBounds();
        $dateBounds = ['before' => $today['start_utc']];

        if ($date !== '') {
            $day = $this->manilaDayBounds($date);
            if (!$day || $day['date'] >= $today['date']) {
                return [];
            }
            $dateBounds['from'] = $day['start_utc'];
            $dateBounds['until'] = $day['end_utc'];
        }

        return $this->queryRequestSearch($search, $status, $documentType, $dateBounds);
    }

    public function deleteArchivedRequest($requestId)
    {
        $today = $this->manilaDayBounds();
        $this->db->transaction();
        try {
            $deleted = $this->db->table('document_requests')
                ->where('id', (int) $requestId)
                ->where('created_at', '<', $today['start_utc'])
                ->delete();
            if ($deleted !== 1) {
                $this->db->roll_back();
                return false;
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }

        return true;
    }

    public function manilaToday()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }

    public function countUserRequestsToday($userId)
    {
        $today = $this->manilaDayBounds();
        return (int) $this->db->table('document_requests')
            ->where('user_id', (int) $userId)
            ->where('created_at', '>=', $today['start_utc'])
            ->where('created_at', '<', $today['end_utc'])
            ->count();
    }

    public function hasActiveRequestForDocument($userId, $documentType)
    {
        $requests = $this->db->table('document_requests')
            ->select('status')
            ->where('user_id', (int) $userId)
            ->where('document_type', $documentType)
            ->get_all();
        foreach ($requests as $request) {
            if (!in_array($request['status'], ['completed', 'rejected'], true)) {
                return true;
            }
        }
        return false;
    }

    public function isPastManilaDate($date)
    {
        $day = $this->manilaDayBounds($date);
        return $day !== null && $day['date'] < $this->manilaToday();
    }

    public function isManilaDate($date)
    {
        return $this->manilaDayBounds($date) !== null;
    }

    public function getRequestRequirementUploads($requestId, $requirements)
    {
        $items = $this->requirementItems($requirements);
        $grouped = array_fill(0, count($items), []);
        if (!$items) {
            return $grouped;
        }

        $files = $this->db->table('request_files')
            ->select('id, request_id, original_name, mime_type, size_bytes, requirement_index, requirement_text, review_status, reviewed_by, reviewed_at, created_at')
            ->where('request_id', (int) $requestId)
            ->get_all();
        foreach ($files as $file) {
            $index = (int) ($file['requirement_index'] ?? -1);
            if (isset($items[$index]) && (string) ($file['requirement_text'] ?? '') === $items[$index]) {
                $grouped[$index][] = $file;
            }
        }
        return $grouped;
    }

    public function hasRequirementUpload($requestId, $requirements, $index)
    {
        $items = $this->requirementItems($requirements);
        if (!isset($items[$index])) {
            return false;
        }
        return $this->db->table('request_files')
            ->where('request_id', (int) $requestId)
            ->where('requirement_index', (int) $index)
            ->where('requirement_text', $items[$index])
            ->where('review_status', 'validated')
            ->limit(1)
            ->count() > 0;
    }

    public function saveRequestRequirementUpload($requestId, $userId, $index, $requirements, $requirementText, array $file)
    {
        $this->db->transaction();
        try {
            $this->setRequirementVerified((int) $requestId, $requirements, (int) $index, false);
            $fileId = (int) $this->db->table('request_files')->insert([
                'request_id' => (int) $requestId,
                'uploaded_by' => (int) $userId,
                'original_name' => $file['original_name'],
                'stored_name' => $file['stored_name'],
                'mime_type' => $file['mime_type'],
                'size_bytes' => (int) $file['size_bytes'],
                'requirement_index' => (int) $index,
                'requirement_text' => $requirementText,
                'review_status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $request = $this->db->table('document_requests')->select('request_number')->where('id', (int) $requestId)->limit(1)->get();
            $uploader = $this->db->table('users')->select('username')->where('id', (int) $userId)->limit(1)->get();
            $requestNumber = $request['request_number'] ?? ('Request #' . (int) $requestId);
            $uploaderName = $uploader['username'] ?? 'A requester';
            $this->notifyAdmins(
                (int) $requestId,
                'New requirement file uploaded',
                $uploaderName . ' uploaded a file for "' . $requirementText . '" on ' . $requestNumber . '.'
            );
            $this->db->commit();
            return $fileId;
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }
    }

    public function reviewRequestRequirementUpload($requestId, $fileId, $index, $requirements, $reviewStatus, $reviewerId, $requesterId, $requestNumber)
    {
        if (!in_array($reviewStatus, ['validated', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose validated or rejected for the requirement upload.');
        }
        $items = $this->requirementItems($requirements);
        if (!isset($items[$index])) {
            throw new InvalidArgumentException('Requirement index is out of range.');
        }
        $latestFile = $this->db->table('request_files')
            ->select('id')
            ->where('request_id', (int) $requestId)
            ->where('requirement_index', (int) $index)
            ->where('requirement_text', $items[$index])
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get();
        if (!$latestFile || (int) $latestFile['id'] !== (int) $fileId) {
            return false;
        }

        $this->db->transaction();
        try {
            $this->db->table('request_files')
                ->where('id', (int) $fileId)
                ->where('request_id', (int) $requestId)
                ->where('requirement_index', (int) $index)
                ->where('requirement_text', $items[$index])
                ->update([
                    'review_status' => $reviewStatus,
                    'reviewed_by' => (int) $reviewerId,
                    'reviewed_at' => gmdate('Y-m-d H:i:s'),
                ]);
            $verifications = $this->setRequirementVerified(
                (int) $requestId,
                $requirements,
                (int) $index,
                $reviewStatus === 'validated'
            );
            if ($reviewStatus === 'validated') {
                $title = 'Requirement validated';
                $body = 'Validated ang requirement na "' . $items[$index] . '" para sa ' . $requestNumber . '.';
            } else {
                $title = 'New requirement file needed';
                $body = 'Hindi naaprubahan ang file para sa "' . $items[$index] . '" sa ' . $requestNumber . '. Mag-upload ng bagong file para maipagpatuloy ang request.';
            }
            $this->createNotification((int) $requesterId, (int) $requestId, $title, $body);
            $this->db->commit();
            return $verifications;
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }
    }

    public function getDashboardStats(array $user)
    {
        $query = $this->db->table('document_requests')
            ->select('status')
            ->select_count('id', 'status_count');
        if ($user['role'] !== 'admin') {
            $query->where('user_id', (int) $user['id']);
        }
        $statusCounts = $query->group_by('status')->get_all();

        $total = 0;
        $active = 0;
        $completed = 0;
        foreach ($statusCounts as $statusCount) {
            $count = (int) $statusCount['status_count'];
            $total += $count;
            if (in_array($statusCount['status'], ['submitted', 'under_review', 'processing', 'ready_for_pickup'], true)) {
                $active += $count;
            }
            if ($statusCount['status'] === 'completed') {
                $completed = $count;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
        ];
    }

    public function createRequest($userId, $fullName, $email, $phone, $address, $documentType, $purpose, array $files = [])
    {
        $requestNumber = 'DQ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $this->db->transaction();
        try {
            $now = gmdate('Y-m-d H:i:s');
            $requestId = (int) $this->db->table('document_requests')->insert([
                'user_id' => $userId,
                'full_name' => $fullName,
                'contact_email' => $email,
                'phone' => $phone,
                'address' => $address,
                'request_number' => $requestNumber,
                'document_type' => $documentType,
                'purpose' => $purpose,
                'status' => 'submitted',
                'progress' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->addUpdate($requestId, $userId, 'submitted', 'Your request was submitted.');
            $this->notifyAdmins($requestId, 'New document request', 'A new request has been submitted: ' . $requestNumber);
            foreach ($files as $file) {
                $this->db->table('request_files')->insert([
                    'request_id' => $requestId,
                    'uploaded_by' => $userId,
                    'original_name' => $file['original_name'],
                    'stored_name' => $file['stored_name'],
                    'mime_type' => $file['mime_type'],
                    'size_bytes' => $file['size_bytes'],
                    'created_at' => $now,
                ]);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }
        return $requestId;
    }

    public function getPendingPaymentRequests($userId)
    {
        $requests = $this->db->table('payment p')
            ->select('r.id, r.request_number, r.created_at')
            ->inner_join('document_requests r', 'r.id = p.request_id')
            ->where('p.user_id', (int) $userId)
            ->where('r.user_id', (int) $userId)
            ->where('p.status', 'pending')
            ->order_by('r.created_at', 'DESC')
            ->get_all();
        return $this->addManilaSubmissionTimes($requests);
    }

    public function findRequestForUser($requestId, array $user)
    {
        $query = $this->db->table('document_requests r')
            ->select($this->requestListColumns() . ', r.requirement_verifications, u.username AS requester_name, u.email AS requester_email')
            ->inner_join('users u', 'u.id = r.user_id')
            ->where('r.id', (int) $requestId);
        if ($user['role'] !== 'admin') {
            $query->where('r.user_id', (int) $user['id']);
        }
        $request = $query->limit(1)->get();
        return $request ? $this->addReleaseDisplayFields($request) : null;
    }

    public function getRequestFeedback($requestId, $userId)
    {
        $feedback = $this->db->table('request_feedback')
            ->select('rating, staff_service_rating, instructions_clarity_rating, status_updates_rating, processing_time_rating, overall_satisfaction_rating, comment, survey_json, created_at')
            ->where('request_id', (int) $requestId)
            ->where('user_id', (int) $userId)
            ->limit(1)
            ->get();
        return $feedback ? $this->decodeFeedbackSurvey($feedback) : null;
    }

    protected function decodeFeedbackSurvey(array $feedback)
    {
        $survey = json_decode((string) ($feedback['survey_json'] ?? ''), true);
        $feedback['survey'] = is_array($survey) ? $survey : null;
        unset($feedback['survey_json']);
        return $feedback;
    }

    public function saveRequestFeedback($requestId, $userId, array $survey)
    {
        $request = $this->db->table('document_requests')
            ->select('request_number')
            ->where('id', (int) $requestId)
            ->limit(1)
            ->get();
        if (!$request) {
            return 0;
        }

        $this->db->transaction();
        try {
            $scores = [];
            foreach (['sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8'] as $key) {
                if ((int) ($survey[$key] ?? 0) > 0) {
                    $scores[] = (int) $survey[$key];
                }
            }
            $rating = count($scores) > 0 ? (int) round(array_sum($scores) / count($scores)) : 1;
            $surveyJson = json_encode($survey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($surveyJson === false) {
                throw new RuntimeException('The feedback questionnaire could not be encoded.');
            }
            $feedbackId = (int) $this->db->table('request_feedback')->insert([
                'request_id' => (int) $requestId,
                'user_id' => (int) $userId,
                'rating' => $rating,
                'staff_service_rating' => (int) ($survey['sqd7'] ?? 0) ?: null,
                'instructions_clarity_rating' => (int) ($survey['sqd4'] ?? 0) ?: null,
                'status_updates_rating' => (int) ($survey['sqd2'] ?? 0) ?: null,
                'processing_time_rating' => (int) ($survey['sqd1'] ?? 0) ?: null,
                'overall_satisfaction_rating' => (int) ($survey['sqd0'] ?? 0) ?: null,
                'comment' => ($survey['suggestions'] ?? '') !== '' ? $survey['suggestions'] : null,
                'survey_json' => $surveyJson,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if ($feedbackId > 0) {
                $this->notifyAdmins(
                    (int) $requestId,
                    'New requester feedback',
                    $request['request_number'] . ' received a Citizen Satisfaction Measurement response with an overall score of ' . number_format(array_sum($scores) / count($scores), 2) . '/5. View the responses in Admin Feedback.'
                );
            }
            $this->db->commit();
            return $feedbackId;
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }
    }

    public function sendFeedbackNotificationEmail(array $request, array $feedback)
    {
        $recipients = $this->db->table('users')
            ->select('email, username')
            ->where('role', 'admin')
            ->where('is_active', 1)
            ->get_all();
        if (!$recipients) {
            error_log('DocuQuest feedback email skipped because no active administrator email was found.');
            return false;
        }

        $email = null;
        try {
            $username = trim(getenv('SMTP_USERNAME') ?: '');
            $password = preg_replace('/\s+/', '', getenv('SMTP_PASSWORD') ?: '');

            $survey = is_array($feedback['survey'] ?? null) ? $feedback['survey'] : [];
            $clientTypes = ['citizen' => 'Mamamayan', 'business' => 'Negosyo', 'government' => 'Pamahalaan'];
            $sexes = ['male' => 'Lalaki', 'female' => 'Babae'];
            $cc1Answers = [
                1 => 'Alam kung ano ang Citizen’s Charter at nakita ang charter ng tanggapan.',
                2 => 'Alam kung ano ang Citizen’s Charter ngunit hindi nakita ang charter ng tanggapan.',
                3 => 'Nalaman lamang ang tungkol dito nang makita ang charter ng tanggapan.',
                4 => 'Hindi alam kung ano ito at walang nakitang charter sa tanggapan.',
            ];
            $cc2Answers = [1 => 'Madaling makita', 2 => 'Medyo madaling makita', 3 => 'Mahirap makita', 4 => 'Hindi talaga makita', 5 => 'Hindi naaangkop'];
            $cc3Answers = [1 => 'Malaki ang naitulong', 2 => 'May naitulong', 3 => 'Hindi nakatulong', 4 => 'Hindi naaangkop'];
            $sqdLabels = [
                'sqd0' => 'Nasiyahan sa serbisyong natanggap',
                'sqd1' => 'Makatwiran ang tagal ng pagproseso',
                'sqd2' => 'Sinunod ang mga kinakailangan at hakbang',
                'sqd3' => 'Madali at simple ang mga hakbang at pagbabayad',
                'sqd4' => 'Madaling nahanap ang impormasyon',
                'sqd5' => 'Makatwiran ang mga bayarin',
                'sqd6' => 'Patas ang pagtrato sa lahat',
                'sqd7' => 'Magalang at matulungin ang mga kawani',
                'sqd8' => 'Nakuha ang kailangan o naipaliwanag ang pagtanggi',
            ];
            $ratingLabels = [0 => 'Hindi naaangkop', 1 => 'Lubos na hindi sumasang-ayon', 2 => 'Hindi sumasang-ayon', 3 => 'Hindi sumasang-ayon o sumasang-ayon', 4 => 'Sumasang-ayon', 5 => 'Lubos na sumasang-ayon'];
            $rows = [
                ['Uri ng kliyente', $clientTypes[$survey['client_type'] ?? ''] ?? 'Hindi nakatala'],
                ['Kasarian', $sexes[$survey['sex'] ?? ''] ?? 'Hindi ibinahagi'],
                ['Edad', $survey['age'] ?? 'Hindi ibinahagi'],
                ['Rehiyon ng tirahan', $survey['region'] ?? 'Hindi ibinahagi'],
                ['Serbisyong ginamit', $request['document_type'] ?? ''],
                ['CC1: Kaalaman sa Citizen’s Charter', $cc1Answers[(int) ($survey['cc1'] ?? 0)] ?? 'Hindi nakatala'],
                ['CC2: Pagkakita sa Citizen’s Charter', $cc2Answers[(int) ($survey['cc2'] ?? 0)] ?? 'Hindi naaangkop'],
                ['CC3: Naitulong ng Citizen’s Charter', $cc3Answers[(int) ($survey['cc3'] ?? 0)] ?? 'Hindi naaangkop'],
            ];
            foreach ($sqdLabels as $key => $label) {
                $value = (int) ($survey[$key] ?? 0);
                $rows[] = [strtoupper($key) . ': ' . $label, $ratingLabels[$value] ?? 'Hindi nakatala'];
            }
            $rows[] = ['Mga mungkahi', ($survey['suggestions'] ?? '') !== '' ? $survey['suggestions'] : 'Walang ibinigay na mungkahi.'];
            if (!empty($survey['email'])) {
                $rows[] = ['Email na ibinahagi', $survey['email']];
            }

            $escape = static function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            };
            $htmlRows = '';
            $textRows = [];
            foreach ($rows as [$label, $value]) {
                $htmlRows .= '<tr><th style="padding:8px;text-align:left;border-bottom:1px solid #ddd">' . $escape($label) . '</th><td style="padding:8px;border-bottom:1px solid #ddd">' . nl2br($escape($value)) . '</td></tr>';
                $textRows[] = $label . ': ' . $value;
            }

            $validRecipients = 0;
            $emailRecipients = [];
            foreach ($recipients as $recipient) {
                $address = trim((string) ($recipient['email'] ?? ''));
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $emailRecipients[] = ['email' => $address, 'name' => (string) ($recipient['username'] ?? '')];
                    $validRecipients++;
                }
            }
            if ($validRecipients < 1) {
                error_log('DocuQuest feedback email skipped because active administrator email addresses are invalid.');
                return false;
            }

            $requestNumber = (string) ($request['request_number'] ?? '');
            $requesterName = (string) ($request['full_name'] ?? '');
            $subject = 'Bagong feedback sa DocuQuest - ' . $requestNumber;
            $htmlBody = '<h2>Bagong Client Satisfaction Measurement response</h2>'
                . '<p><strong>Kahilingan:</strong> ' . $escape($requestNumber) . '<br><strong>Kliyente:</strong> ' . $escape($requesterName) . '</p>'
                . '<table style="width:100%;border-collapse:collapse">' . $htmlRows . '</table>';
            $textBody = "Bagong Client Satisfaction Measurement response\nKahilingan: " . $requestNumber
                . "\nKliyente: " . $requesterName . "\n\n" . implode("\n", $textRows);

            $provider = strtolower(trim((string) (getenv('EMAIL_PROVIDER') ?: 'brevo')));
            if ($provider === 'brevo') {
                $apiKey = trim((string) (getenv('BREVO_API_KEY') ?: ''));
                if ($apiKey === '') {
                    throw new RuntimeException('BREVO_API_KEY is required when EMAIL_PROVIDER=brevo.');
                }
                $from = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: $username));
                if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('MAIL_FROM_ADDRESS must be valid when EMAIL_PROVIDER=brevo.');
                }
                $payload = [
                    'sender' => ['email' => $from, 'name' => trim((string) (getenv('MAIL_FROM_NAME') ?: 'DocuQuest'))],
                    'to' => $emailRecipients,
                    'subject' => $subject,
                    'htmlContent' => $htmlBody,
                    'textContent' => $textBody,
                ];
                $handle = curl_init('https://api.brevo.com/v3/smtp/email');
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_HTTPHEADER => [
                        'api-key: ' . $apiKey,
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                ]);
                $response = curl_exec($handle);
                $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                curl_close($handle);
                if (!is_string($response) || $status < 200 || $status >= 300) {
                    throw new RuntimeException('Brevo HTTP ' . $status . ': ' . (is_string($response) ? trim($response) : 'No response.'));
                }
                return true;
            }

            if ($provider === 'resend') {
                $apiKey = trim((string) (getenv('RESEND_API_KEY') ?: ''));
                if ($apiKey === '') {
                    throw new RuntimeException('RESEND_API_KEY is required when EMAIL_PROVIDER=resend.');
                }
                $from = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: $username));
                if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('MAIL_FROM_ADDRESS must be valid when EMAIL_PROVIDER=resend.');
                }
                $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'DocuQuest'));
                $payload = [
                    'from' => $fromName !== '' ? $fromName . ' <' . $from . '>' : $from,
                    'to' => array_column($emailRecipients, 'email'),
                    'subject' => $subject,
                    'html' => $htmlBody,
                    'text' => $textBody,
                ];
                $handle = curl_init('https://api.resend.com/emails');
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 20,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $apiKey,
                        'Content-Type: application/json',
                    ],
                ]);
                $response = curl_exec($handle);
                $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                curl_close($handle);
                if (!is_string($response) || $status < 200 || $status >= 300) {
                    throw new RuntimeException('Resend HTTP ' . $status . ': ' . (is_string($response) ? trim($response) : 'No response.'));
                }
                return true;
            }

            if ($username === '' || $password === '') {
                throw new RuntimeException('SMTP_USERNAME and SMTP_PASSWORD are required when EMAIL_PROVIDER=smtp.');
            }
            $email = new \PHPMailer\PHPMailer\PHPMailer(true);
            $email->isSMTP();
            $email->Host = trim((string) (getenv('SMTP_HOST') ?: 'smtp.gmail.com')) ?: 'smtp.gmail.com';
            $email->SMTPAuth = true;
            $email->SMTPAutoTLS = true;
            $email->AuthType = 'LOGIN';
            $email->Username = $username;
            $email->Password = $password;
            $smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
            $smtpSecure = strtolower(trim((string) (getenv('SMTP_SECURE') ?: ($smtpPort === 465 ? 'ssl' : 'tls'))));
            if ($smtpPort === 465 || $smtpSecure === 'ssl') {
                $smtpPort = 465;
                $email->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $email->SMTPAutoTLS = false;
            } else {
                $smtpPort = 587;
                $email->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $email->Port = $smtpPort;
            $email->Timeout = (int) (getenv('SMTP_TIMEOUT') ?: 30);
            $email->CharSet = 'UTF-8';
            $email->isHTML(true);
            $email->setFrom($username, getenv('MAIL_FROM_NAME') ?: 'DocuQuest');
            foreach ($emailRecipients as $recipient) {
                $email->addAddress($recipient['email'], $recipient['name']);
            }
            $email->Subject = $subject;
            $email->Body = $htmlBody;
            $email->AltBody = $textBody;
            $email->send();
            return true;
        } catch (Throwable $exception) {
            $mailerError = $email instanceof \PHPMailer\PHPMailer\PHPMailer ? trim((string) $email->ErrorInfo) : '';
            error_log('DocuQuest feedback email failed: ' . $exception->getMessage()
                . ($mailerError !== '' ? ' | SMTP: ' . $mailerError : ''));
            return false;
        }
    }

    public function updateRequestStatus(array $request, $status, $note, $adminId, $requirements = null, $releaseAt = null)
    {
        if (!$this->canTransitionRequestStatus($request['status'] ?? '', $status)) {
            throw new DomainException('Request status must advance to the next progress step.');
        }

        $payment = $this->getRequestPayment((int) $request['id']);
        if (in_array($status, ['approved', 'ready_for_pickup', 'completed'], true)) {
            if (!$this->isPaymentSatisfied($payment)) {
                throw new DomainException('The requester must complete payment before the certified copy can be approved, released, or completed.');
            }
        }

        $label = $this->statusMap[$status]['label'];
        $progress = $this->statusMap[$status]['progress'];
        $requirements = $requirements === null
            ? ($request['requirements'] ?? null)
            : (trim($requirements) !== '' ? trim($requirements) : null);
        $releaseAt = $releaseAt === null
            ? ($request['release_at'] ?? null)
            : ($releaseAt !== '' ? $releaseAt : null);
        if (in_array($status, ['ready_for_pickup', 'completed'], true)
            && trim((string) $requirements) !== '') {
            if (!$this->areRequirementsVerified((int) $request['id'], $requirements)) {
                throw new DomainException('Upload and verify every listed requirement before release or completion.');
            }
        }

        $this->db->transaction();
        try {
            $this->db->table('document_requests')
                ->where('id', (int) $request['id'])
                ->update([
                    'status' => $status,
                    'progress' => $progress,
                    'admin_note' => $note ?: null,
                    'requirements' => $requirements,
                    'requirement_verifications' => trim((string) ($request['requirements'] ?? '')) !== trim((string) $requirements) ? null : ($request['requirement_verifications'] ?? null),
                    'release_at' => $releaseAt,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
            $this->addUpdate((int) $request['id'], $adminId, $status, $note ?: $label, $requirements, $releaseAt);
            $downloadNotice = '';
            if (($payment['status'] ?? '') === 'paid') {
                $downloadNotice = ' Kindly check your email for the downloadable payment receipt';
                if (in_array($status, ['approved', 'completed'], true)) {
                    $downloadNotice .= ' and certified copy';
                }
                $downloadNotice .= '.';
            }
            $requirementAttachmentNotice = '';
            if ($status === 'completed') {
                $completionAttachments = $this->getRequesterRequirementUploads(
                    (int) $request['id'],
                    (int) $request['user_id'],
                    (string) $requirements
                );
                if (count($completionAttachments) > 0) {
                    $requirementAttachmentNotice = ' Uploaded requirement files are attached to the completion email.';
                }
            }
            $this->createNotification(
                (int) $request['user_id'],
                (int) $request['id'],
                'Request status updated',
                $request['request_number'] . ' is now ' . $label . '.'
                . ($status === 'ready_for_pickup' && $releaseAt ? ' Nakatakda ang pagkuha ng dokumento sa ' . $this->manilaReleaseDetails($releaseAt)['label'] . ' Philippine time.' : '')
                . ($status === 'completed' ? ' Salamat sa pagtitiwala sa DocuQuest.' : '')
                . (trim((string) ($requirements ?? '')) !== '' ? ' Verified requirements: ' . trim((string) $requirements) . '.' : '')
                . $requirementAttachmentNotice
                . $downloadNotice
            );
            $previousRequirements = trim((string) ($request['requirements'] ?? ''));
            if ($requirements !== null && $requirements !== '' && $requirements !== $previousRequirements) {
                $this->createNotification(
                    (int) $request['user_id'],
                    (int) $request['id'],
                    'Requirements requested',
                    $request['request_number'] . ' has new requirements. Open request progress to upload a file for each listed item. Use Messages for other concerns.'
                );
            }
            if ($status === 'approved') {
                $this->ensureCertifiedCopy((int) $request['id'], $adminId, $request);
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }

        $request['status'] = $status;
        $request['requirements'] = $requirements;
        $request['release_at'] = $releaseAt;
        $payment = $this->getRequestPayment((int) $request['id']);
        return $this->sendRequestUpdateEmail($request, $label, $progress, $note, $payment);
    }

    public function getRequestUpdates($requestId)
    {
        $updates = $this->db->table('request_updates ru')
            ->select('ru.id, ru.request_id, ru.actor_id, ru.status, ru.note, ru.requirements, ru.release_at, ru.created_at, u.username AS actor_name')
            ->inner_join('users u', 'u.id = ru.actor_id')
            ->where('ru.request_id', (int) $requestId)
            ->order_by('ru.created_at', 'DESC')
            ->get_all();
        foreach ($updates as &$update) {
            $release = $this->manilaReleaseDetails($update['release_at'] ?? null);
            $update['release_at_local'] = $release['local'];
            $update['release_at_label'] = $release['label'];
        }
        unset($update);
        return $updates;
    }

    private function addReleaseDisplayFields(array $request)
    {
        $release = $this->manilaReleaseDetails($request['release_at'] ?? null);
        $request['release_at_local'] = $release['local'];
        $request['release_at_label'] = $release['label'];
        return $request;
    }

    protected function manilaReleaseDetails($releaseAt)
    {
        if (!is_string($releaseAt) || $releaseAt === '') {
            return ['local' => '', 'label' => ''];
        }

        $utc = new DateTimeZone('UTC');
        $release = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $releaseAt, $utc);
        if (!$release) {
            return ['local' => '', 'label' => ''];
        }

        $release = $release->setTimezone(new DateTimeZone('Asia/Manila'));
        return [
            'local' => $release->format('Y-m-d\\TH:i'),
            'label' => $release->format('F j, Y, g:i A'),
        ];
    }

    protected function findCertifiedCopy($requestId)
    {
        return $this->db->table('request_files')
            ->select('stored_name, original_name')
            ->where('request_id', (int) $requestId)
            ->like('original_name', '%-copy-certified.pdf')
            ->limit(1)
            ->get();
    }

    private function ensureCertifiedCopy($requestId, $adminId, array $request)
    {
        $existingCopy = $this->findCertifiedCopy($requestId);
        if ($existingCopy) {
            $storage = new Document_storage();
            $storage->delete($existingCopy['stored_name']);
            $this->db->table('request_files')
                ->where('request_id', (int) $requestId)
                ->like('original_name', '%-copy-certified.pdf')
                ->delete();
        }

        $storedName = md5('docuquest-certified-' . $requestId . '-' . $request['request_number']) . '.pdf';
        $originalName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $request['request_number'])
            . '-copy-certified.pdf';
        $temporaryPdf = tempnam(sys_get_temp_dir(), 'docuquest-certified-');
        if ($temporaryPdf === false) {
            throw new RuntimeException('A temporary PDF file could not be created.');
        }

        $amount = $this->getRequestPayment($requestId);
        $fee = $amount ? 'PHP ' . number_format((float) $amount['amount'], 2) : 'To be determined by the Municipal Office';
        $renderer = $this->call->library('certified_copy_pdf_renderer');
        $pdfSize = $renderer->renderToFile($request, $fee, $temporaryPdf);

        $storage = new Document_storage();
        if (!$storage->put($temporaryPdf, $storedName, 'application/pdf')) {
            unlink($temporaryPdf);
            throw new RuntimeException('The approved certified copy could not be stored.');
        }
        unlink($temporaryPdf);
        $this->db->table('request_files')->insert([
            'request_id' => $requestId,
            'uploaded_by' => $adminId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => 'application/pdf',
            'size_bytes' => (int) $pdfSize,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function getRequestFiles($requestId)
    {
        return $this->db->table('request_files')
            ->select('id, request_id, original_name, mime_type, size_bytes, created_at')
            ->where('request_id', (int) $requestId)
            ->order_by('id', 'ASC')
            ->get_all();
    }

    public function getCertifiedCopies($requestId)
    {
        return $this->db->table('request_files')
            ->select('id, request_id, original_name, mime_type, size_bytes, created_at')
            ->where('request_id', (int) $requestId)
            ->like('original_name', '%copy-certified.pdf')
            ->order_by('id', 'ASC')
            ->get_all();
    }

    public function findRequestFileForUser($fileId, array $user)
    {

        $query = $this->db->table('request_files f')
            ->select('f.id, f.request_id, f.uploaded_by, f.original_name, f.stored_name, f.mime_type, f.size_bytes, f.created_at')
            ->inner_join('document_requests r', 'r.id = f.request_id')
            ->where('f.id', (int) $fileId);
        if ($user['role'] !== 'admin') {
            $query->where('r.user_id', (int) $user['id']);
        }
        return $query->limit(1)->get() ?: null;
    }

    public function findMessageFileForUser($fileId, array $user)
    {
        $query = $this->db->table('request_message_files f')
            ->select('f.id, f.message_id, f.original_name, f.stored_name, f.mime_type, f.size_bytes, f.created_at')
            ->inner_join('request_messages m', 'm.id = f.message_id')
            ->inner_join('document_requests r', 'r.id = m.request_id')
            ->where('f.id', (int) $fileId);
        if ($user['role'] !== 'admin') {
            $query->where('r.user_id', (int) $user['id']);
        }
        return $query->limit(1)->get() ?: null;
    }

    public function getMessages($requestId, $includeAttachments = true)
    {
        $columns = 'm.id, m.request_id, m.sender_id, m.body, m.created_at, u.username AS sender_name, u.role AS sender_role';
        if ($includeAttachments) {
            $columns .= ', f.id AS attachment_id, f.original_name AS attachment_original_name, f.mime_type AS attachment_mime_type';
        }
        $query = $this->db->table('request_messages m')
            ->select($columns)
            ->inner_join('users u', 'u.id = m.sender_id')
            ->where('m.request_id', (int) $requestId);
        if ($includeAttachments) {
            $query->left_join('request_message_files f', 'f.message_id = m.id');
        }
        $messages = $query->order_by('m.created_at', 'ASC')->get_all();
        usort($messages, static function ($left, $right) {
            return [$left['created_at'], (int) $left['id']] <=> [$right['created_at'], (int) $right['id']];
        });
        return $messages;
    }

    public function getRequesterRequirementUploads($requestId, $requesterId, $requirements = '')
    {
        if (!$requesterId) return [];
        $messageFiles = $this->db->table('request_message_files f')
            ->select('f.original_name, f.stored_name, f.mime_type, f.size_bytes, f.created_at')
            ->inner_join('request_messages m', 'm.id = f.message_id')
            ->where('m.request_id', (int) $requestId)
            ->where('m.sender_id', (int) $requesterId)
            ->order_by('f.id', 'ASC')
            ->get_all();
        $requirementFiles = $this->db->table('request_files')
            ->select('original_name, stored_name, mime_type, size_bytes, requirement_index, requirement_text, created_at')
            ->where('request_id', (int) $requestId)
            ->where('uploaded_by', (int) $requesterId)
            ->where('requirement_index', '>=', 0)
            ->order_by('id', 'ASC')
            ->get_all();
        $requirementItems = $this->requirementItems($requirements);
        $requirementFiles = array_values(array_filter($requirementFiles, static function ($file) use ($requirementItems) {
            $index = (int) ($file['requirement_index'] ?? -1);
            return isset($requirementItems[$index])
                && (string) ($file['requirement_text'] ?? '') === $requirementItems[$index];
        }));
        return array_merge($messageFiles, $requirementFiles);
    }

    public function hasRequesterRequirementUpload($requestId, $requesterId)
    {
        return $this->db->table('request_messages m')
            ->inner_join('request_message_files f', 'f.message_id = m.id')
            ->where('m.request_id', (int) $requestId)
            ->where('m.sender_id', (int) $requesterId)
            ->limit(1)
            ->count() > 0;
    }

    public function getRequirementVerifications($requestId, $requirements)
    {
        $request = $this->db->table('document_requests')->where('id', (int) $requestId)->limit(1)->get();
        $decoded = json_decode((string) ($request['requirement_verifications'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $itemCount = count($this->requirementItems($requirements));
        return array_values(array_unique(array_filter(array_map('intval', $decoded), function ($index) use ($itemCount) {
            return $index >= 0 && $index < $itemCount;
        })));
    }

    public function setRequirementVerified($requestId, $requirements, $index, $verified)
    {
        $items = $this->requirementItems($requirements);
        if (!isset($items[$index])) {
            throw new InvalidArgumentException('Requirement index is out of range.');
        }
        $verifiedIndexes = $this->getRequirementVerifications($requestId, $requirements);
        if ($verified && !in_array($index, $verifiedIndexes, true)) {
            $verifiedIndexes[] = $index;
        }
        if (!$verified) {
            $verifiedIndexes = array_values(array_filter($verifiedIndexes, function ($value) use ($index) {
                return $value !== $index;
            }));
        }
        sort($verifiedIndexes);
        $this->db->table('document_requests')->where('id', (int) $requestId)->update([
            'requirement_verifications' => $verifiedIndexes ? json_encode($verifiedIndexes) : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $verifiedIndexes;
    }

    public function areRequirementsVerified($requestId, $requirements)
    {
        $items = $this->requirementItems($requirements);
        if (!$items) {
            return true;
        }
        $verified = $this->getRequirementVerifications($requestId, $requirements);
        return count($verified) === count($items);
    }

    public function requirementItems($requirements)
    {
        $items = preg_split('/\r\n|\r|\n/', trim((string) $requirements));
        return array_values(array_filter(array_map(function ($item) {
            return trim(preg_replace('/^(?:[-*•]|\d+[.)])\s*/u', '', trim((string) $item)));
        }, $items ?: []), function ($item) {
            return $item !== '';
        }));
    }

    public function sendMessage(array $request, array $sender, $body, ?array $attachment = null)
    {
        $this->db->transaction();
        try {
            $createdAt = gmdate('Y-m-d H:i:s');
            $messageId = (int) $this->db->table('request_messages')->insert([
                'request_id' => (int) $request['id'],
                'sender_id' => (int) $sender['id'],
                'body' => $body,
                'created_at' => $createdAt,
            ]);
            if ($attachment) {
                $this->db->table('request_message_files')->insert([
                    'message_id' => $messageId,
                    'original_name' => $attachment['original_name'],
                    'stored_name' => $attachment['stored_name'],
                    'mime_type' => $attachment['mime_type'],
                    'size_bytes' => $attachment['size_bytes'],
                    'created_at' => $createdAt,
                ]);
            }

            if ($sender['role'] === 'admin') {
                $recipientIds = [(int) $request['user_id']];
            } else {
                $recipientIds = $this->getAdminIds();
            }
            foreach ($recipientIds as $recipientId) {
                $this->createNotification(
                    $recipientId,
                    (int) $request['id'],
                    'New message on ' . $request['request_number'],
                    $sender['username'] . ' sent a message about this request.'
                );
            }
            $this->db->commit();
            return $messageId;
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }
    }

    protected function requestListColumns()
    {
        return 'r.id, r.user_id, r.full_name, r.contact_email, r.phone, r.address, r.request_number, '
            . 'r.document_type, r.purpose, r.status, r.progress, r.admin_note, r.requirements, '
            . 'r.release_at, r.created_at, r.updated_at';
    }

    private function queryRequestSearch($search, $status, $documentType, array $dateBounds = [])
    {
        $query = $this->db->table('document_requests r')
            ->select($this->requestListColumns() . ', u.username AS requester_name, u.email AS requester_email')
            ->inner_join('users u', 'u.id = r.user_id');
        if (isset($dateBounds['before'])) {
            $query->where('r.created_at', '<', $dateBounds['before']);
        }
        if (isset($dateBounds['from'], $dateBounds['until'])) {
            $query->where('r.created_at', '>=', $dateBounds['from'])
                ->where('r.created_at', '<', $dateBounds['until']);
        }
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->grouped(static function ($builder) use ($term) {
                $builder->like('r.request_number', $term)
                    ->or_like('r.document_type', $term)
                    ->or_like('r.purpose', $term)
                    ->or_like('r.full_name', $term)
                    ->or_like('r.contact_email', $term)
                    ->or_like('r.phone', $term)
                    ->or_like('r.address', $term)
                    ->or_like('u.username', $term)
                    ->or_like('u.email', $term);
            });
        }
        if (isset($this->statusMap[$status])) {
            $query->where('r.status', $status);
        }
        if (in_array($documentType, $this->documentTypes(), true)) {
            $query->where('r.document_type', $documentType);
        }
        $requests = $query->get_all();
        usort($requests, static function ($left, $right) {
            return [$right['created_at'], (int) $right['id']] <=> [$left['created_at'], (int) $left['id']];
        });
        return $this->addManilaSubmissionTimes($requests);
    }

    protected function manilaDayBounds($date = null)
    {
        $manila = new DateTimeZone('Asia/Manila');
        if ($date === null) {
            $start = new DateTimeImmutable('today', $manila);
        } else {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $manila);
            if (!$start || $start->format('Y-m-d') !== $date) {
                return null;
            }
        }

        $utc = new DateTimeZone('UTC');
        return [
            'date' => $start->format('Y-m-d'),
            'start_utc' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end_utc' => $start->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    protected function addManilaSubmissionTimes(array $requests)
    {
        $utc = new DateTimeZone('UTC');
        $manila = new DateTimeZone('Asia/Manila');
        foreach ($requests as &$request) {
            $submittedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $request['created_at'], $utc);
            if (!$submittedAt) {
                continue;
            }
            $submittedAt = $submittedAt->setTimezone($manila);
            $request['submitted_date'] = $submittedAt->format('Y-m-d');
            $request['submitted_day_label'] = $submittedAt->format('l, F j, Y');
            $request['submitted_time'] = $submittedAt->format('g:i A');
            $request['submitted_at'] = $submittedAt->format('F j, Y, g:i A');
        }
        unset($request);

        return $requests;
    }

}
