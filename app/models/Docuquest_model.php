<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Docuquest_model extends Model
{
    private const LOGIN_ATTEMPT_LIMIT = 3;
    private const LOGIN_ATTEMPT_WINDOW_SECONDS = 1800;
    private const LOGIN_LOCK_SECONDS = 1800;

    protected $statusMap = [
        'submitted' => ['label' => 'Submitted', 'progress' => 10],
        'under_review' => ['label' => 'Under review', 'progress' => 30],
        'processing' => ['label' => 'Processing', 'progress' => 60],
        'approved' => ['label' => 'Approved / Copy Certified', 'progress' => 80],
        'ready_for_pickup' => ['label' => 'Ready for release', 'progress' => 85],
        'completed' => ['label' => 'Completed', 'progress' => 100],
        'rejected' => ['label' => 'Rejected', 'progress' => 100],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function statuses()
    {
        return $this->statusMap;
    }

    public function canTransitionRequestStatus($currentStatus, $nextStatus)
    {
        if (in_array($currentStatus, ['completed', 'rejected'], true)) {
            return false;
        }
        if ($nextStatus === 'rejected') {
            return true;
        }

        $progression = array_values(array_filter(array_keys($this->statusMap), static function ($status) {
            return $status !== 'rejected';
        }));
        $currentIndex = array_search($currentStatus, $progression, true);
        return $currentIndex !== false
            && isset($progression[$currentIndex + 1])
            && $progression[$currentIndex + 1] === $nextStatus;
    }

    public function documentTypes()
    {
        return [
            'Application for Marriage License',
            'Authentication of Record',
            'Endorsement',
            'Issuance of Certificate of Birth/Marriage/Death on Municipal Forms (1A, 2B, 3C)',
            'Petition for Correction of Clerical Error (RA 9048/10172) or Change of First Name (RA 9048)',
            'Registration of Death',
            'Registration of Live Birth',
            'Supplemental Report/Annotation (Birth/Marriage/Death)',
        ];
    }

    public function isValidDocumentType($documentType)
    {
        return is_string($documentType) && in_array($documentType, $this->documentTypes(), true);
    }

    public function barangays()
    {
        $barangays = [
            'Adrialuna',
            'Andres Ylagan (Mag-asawang Tubig)',
            'Antipolo (Parusan)',
            'Apitong',
            'Arangin',
            'Aurora',
            'Bacungan',
            'Bagong Buhay',
            'Balite',
            'Bancuro',
            'Banuton',
            'Barcenaga',
            'Bayani',
            'Buhangin',
            'Caburo',
            'Concepcion',
            'Dao',
            'Del Pilar',
            'Estrella',
            'Evangelista',
            'Gamao',
            'General Esco',
            'Herrera',
            'Inarawan',
            'Kalinisan',
            'Laguna (Balansig)',
            'Mabini',
            'Magtibay',
            'Mahabang Parang',
            'Malabo',
            'Malaya',
            'Malinao',
            'Malvar',
            'Masagana',
            'Masaguing',
            'Melgar A (San Jose Dos)',
            'Melgar B (San Jose Dos)',
            'Metolza',
            'Montelago (Pungao)',
            'Montemayor (Lugta)',
            'Motoderazo',
            'Mulawin',
            'Nag-Iba I',
            'Nag-Iba II',
            'Pagkakaisa',
            'Paitan',
            'Paniquian',
            'Pinagsabangan I',
            'Pinagsabangan II',
            'Pinahan',
            'Poblacion I (Barangay I)',
            'Poblacion II (Barangay II)',
            'Poblacion III (Barangay III)',
            'Sampaguita',
            'San Agustin I',
            'San Agustin II (Ilaya)',
            'San Andres',
            'San Antonio',
            'San Carlos',
            'San Isidro (Calaguimay)',
            'San Jose (San Jose Uno)',
            'San Luis',
            'San Nicolas',
            'San Pedro',
            'Santa Cruz',
            'Santa Isabel (Mapantat)',
            'Santa Maria',
            'Santiago',
            'Santo Nino',
            'Tagumpay',
            'Tigkan',
        ];
        sort($barangays, SORT_NATURAL | SORT_FLAG_CASE);
        return $barangays;
    }

    public function normalizePhilippineMobile($phone)
    {
        if (!is_string($phone)) {
            return null;
        }

        if (preg_match('/\A\+639[0-9]{9}\z/', $phone) === 1) {
            return $phone;
        }
        if (preg_match('/\A9[0-9]{9}\z/', $phone) === 1) {
            return '+63' . $phone;
        }

        return null;
    }

    public function findUserByEmail($email)
    {
        return $this->db->table('users')
            ->select('id, username, email, password, role, is_active')
            ->where('email', $email)
            ->limit(1)
            ->get() ?: null;
    }

    

    

    

    

    public function findUserByUsername($username)
    {
        return $this->db->table('users')
            ->select('id')
            ->where('username', $username)
            ->limit(1)
            ->get() ?: null;
    }

    public function findUserById($id)
    {
        $user = $this->db->table('users')
            ->select('id, username, email, role, is_active')
            ->where('id', (int) $id)
            ->limit(1)
            ->get();
        return $user && (int) $user['is_active'] === 1 ? $user : null;
    }

    

    public function getUserRecordForUpdate($id)
    {
        return $this->db->table('users')
            ->select('id, username, email, password, role, is_active')
            ->where('id', (int) $id)
            ->limit(1)
            ->get() ?: null;
    }

    public function updateUserProfile($id, $username, $email, $passwordHash = null)
    {
        $existingUsername = $this->findUserByUsername($username);
        if ($existingUsername && (int) $existingUsername['id'] !== (int) $id) {
            throw new RuntimeException('An account with this name already exists. Please use another name.');
        }

        $existingEmail = $this->findUserByEmail($email);
        if ($existingEmail && (int) $existingEmail['id'] !== (int) $id) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $changes = ['username' => $username, 'email' => $email];
        if ($passwordHash !== null) {
            $changes['password'] = $passwordHash;
        }
        $this->db->table('users')
            ->where('id', (int) $id)
            ->update($changes);

        return $this->findUserById((int) $id);
    }

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    public function getHomepageSettingsRecord()
    {
        return $this->db->table('homepage_settings')
            ->select('content_json, hero_background_stored_name, hero_background_mime_type, hero_seal_stored_name, hero_seal_mime_type, updated_by, updated_at')
            ->where('id', 1)
            ->limit(1)
            ->get() ?: null;
    }

    public function getHomepageImage($slot)
    {
        $columns = [
            'hero_background' => ['hero_background_stored_name', 'hero_background_mime_type'],
            'hero_seal' => ['hero_seal_stored_name', 'hero_seal_mime_type'],
        ];
        if (!isset($columns[$slot])) return null;

        [$storedColumn, $mimeColumn] = $columns[$slot];
        $image = $this->db->table('homepage_settings')
            ->select($storedColumn . ' AS stored_name, ' . $mimeColumn . ' AS mime_type')
            ->where('id', 1)
            ->limit(1)
            ->get();
        return $image && $image['stored_name'] ? $image : null;
    }

    public function saveHomepageSettings(array $content, array $images, array $clearImages, $adminId)
    {
        $existing = $this->getHomepageSettingsRecord();
        $heroBackgroundName = !empty($clearImages['hero_background'])
            ? null
            : ($images['hero_background']['stored_name'] ?? ($existing['hero_background_stored_name'] ?? null));
        $heroBackgroundMime = !empty($clearImages['hero_background'])
            ? null
            : ($images['hero_background']['mime_type'] ?? ($existing['hero_background_mime_type'] ?? null));
        $heroSealName = !empty($clearImages['hero_seal'])
            ? null
            : ($images['hero_seal']['stored_name'] ?? ($existing['hero_seal_stored_name'] ?? null));
        $heroSealMime = !empty($clearImages['hero_seal'])
            ? null
            : ($images['hero_seal']['mime_type'] ?? ($existing['hero_seal_mime_type'] ?? null));

        $settings = [
            'content_json' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'hero_background_stored_name' => $heroBackgroundName,
            'hero_background_mime_type' => $heroBackgroundMime,
            'hero_seal_stored_name' => $heroSealName,
            'hero_seal_mime_type' => $heroSealMime,
            'updated_by' => (int) $adminId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        $this->db->transaction();
        try {
            if ($existing) {
                $this->db->table('homepage_settings')->where('id', 1)->update($settings);
            } else {
                try {
                    $this->db->table('homepage_settings')->insert(['id' => 1] + $settings);
                } catch (Throwable $exception) {
                    $existing = $this->getHomepageSettingsRecord();
                    if (!$existing) {
                        throw $exception;
                    }
                    $this->db->table('homepage_settings')->where('id', 1)->update($settings);
                }
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->roll_back();
            throw $exception;
        }

        return $existing;
    }

    

    

    

    

    

    

    public function sendPaymentDueEmail(array $request, array $payment)
    {
        $status = $this->statusMap[$request['status'] ?? 'submitted'] ?? $this->statusMap['submitted'];
        return $this->sendRequestUpdateEmail($request, $status['label'], $status['progress'], '', $payment);
    }

    protected function sendRequestUpdateEmail(array $request, $label, $progress, $note, ?array $payment)
    {
        $recipient = $request['requester_email'] ?? $request['contact_email'] ?? '';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('DocuQuest request update email skipped because the requester email is invalid.');
            return false;
        }

        try {
            $username = trim(getenv('SMTP_USERNAME') ?: '');
            $password = getenv('SMTP_PASSWORD') ?: '';
            if ($username === '' || $password === '') {
                throw new RuntimeException('SMTP_USERNAME and SMTP_PASSWORD are required.');
            }

            $email = new \PHPMailer\PHPMailer\PHPMailer(true);
            $email->isSMTP();
            $email->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $email->SMTPAuth = true;
            $email->Username = $username;
            $email->Password = $password;
            $email->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $email->Port = (int) (getenv('SMTP_PORT') ?: 587);
            $email->Timeout = 10;
            $email->CharSet = 'UTF-8';
            $email->isHTML(true);
            $email->setFrom($username, getenv('MAIL_FROM_NAME') ?: 'DocuQuest');
            $email->addAddress($recipient, $request['full_name'] ?? '');
            $email->Subject = 'DocuQuest request update - ' . $request['request_number'];
            if (($payment['status'] ?? '') === 'paid') {
                $receiptPdf = $this->renderPaymentReceiptPdf($request, $payment);
                $receiptName = ($request['request_number'] ?? 'request') . '-payment-receipt.pdf';
                $email->addStringAttachment($receiptPdf, $receiptName, 'base64', 'application/pdf');
            }
            $certificate = null;
            if (($request['status'] ?? '') === 'completed') {
                $storage = new Document_storage();
                foreach ($this->getRequesterRequirementUploads(
                    (int) $request['id'],
                    (int) ($request['user_id'] ?? 0),
                    $request['requirements'] ?? ''
                ) as $uploadedFile) {
                    $contents = $storage->get($uploadedFile['stored_name']);
                    if ($contents !== null) {
                        $email->addStringAttachment($contents, $uploadedFile['original_name'], 'base64', $uploadedFile['mime_type'] ?: 'application/octet-stream');
                    }
                }
            }
            if (in_array(($request['status'] ?? ''), ['approved', 'ready_for_pickup', 'completed'], true)) {
                $certificate = $this->findCertifiedCopy((int) $request['id']);
                if ($certificate) {
                    $storage = new Document_storage();
                    $contents = $storage->get($certificate['stored_name']);
                    if ($contents !== null) {
                        $email->addStringAttachment($contents, $certificate['original_name'], 'base64', 'application/pdf');
                    }
                }
            }
            $release = $this->manilaReleaseDetails($request['release_at'] ?? null);
            $escape = static function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            };
            $baseUrl = rtrim((string) config_item('base_url'), '/') . '/';
            $paymentUrl = $baseUrl . 'login';
            $homepageImage = $this->getHomepageImage('hero_background');
            $heroImageUrl = $homepageImage
                ? $baseUrl . 'api/homepage/image?slot=hero_background'
                : '';
            $paymentBlock = '';
            $paymentText = 'No payment amount has been set.';
            if ($payment) {
                $paymentStatus = ($payment['status'] ?? '') === 'waived'
                    ? 'No payment required'
                    : ucfirst((string) ($payment['status'] ?? 'pending'));
                $paymentAmount = 'PHP ' . number_format((float) ($payment['amount'] ?? 0), 2);
                $paymentText = ($payment['status'] ?? '') === 'waived'
                    ? 'No payment required (' . $paymentAmount . ')'
                    : $paymentAmount . ' - ' . $paymentStatus;
                $paymentBlock = '<tr><td style="padding:16px 20px;border-bottom:1px solid #e5d8b7">'
                    . '<div style="font-size:11px;letter-spacing:1px;color:#927443;font-weight:bold">PAYMENT</div>'
                    . '<div style="margin-top:6px;font-size:18px;color:#302c25;font-weight:bold">' . $escape($paymentAmount) . '</div>'
                    . '<div style="margin-top:4px;color:#716b5d">Status: ' . $escape($paymentStatus) . '</div>'
                    . '</td></tr>';
                if (($payment['status'] ?? '') === 'pending') {
                    $paymentBlock .= '<tr><td style="padding:14px 20px;background:#f8f1df;color:#66501c">'
                        . 'Sign in to your requester page and open this request to pay securely with PayMongo.'
                        . '</td></tr>';
                } elseif (($payment['status'] ?? '') === 'waived') {
                    $paymentBlock .= '<tr><td style="padding:14px 20px;background:#edf5e9;color:#32613a">'
                        . 'No payment is required for this request.'
                        . '</td></tr>';
                }
            }

            $heroStyle = $heroImageUrl !== ''
                ? 'background-image:linear-gradient(100deg,rgba(26,39,31,.91),rgba(91,65,22,.72)),url(&quot;' . $escape($heroImageUrl) . '&quot;);background-position:center;background-size:cover;'
                : 'background-color:#173d31;';
            $updatedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('F j, Y, g:i A');
            $html = '<!doctype html><html><body style="margin:0;padding:24px 8px;background:#eee9dc;font-family:Arial,Helvetica,sans-serif;color:#39352d">'
                . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
                . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#fffefa;border:1px solid #dfd0a9;border-radius:8px;overflow:hidden">'
                . '<tr><td style="padding:28px 24px;' . $heroStyle . 'color:#fff">'
                . '<div style="font-size:11px;letter-spacing:2px;color:#f0d27a;font-weight:bold">DOCUQUEST · REQUEST UPDATE</div>'
                . '<h1 style="margin:10px 0 4px;font-size:25px;line-height:1.25;color:#fff">Your request has an update</h1>'
                . '<div style="font-size:13px;color:#f4ead0">' . $escape($request['request_number'] ?? '') . '</div>'
                . '</td></tr><tr><td style="padding:20px 20px 6px">'
                . '<p style="margin:0 0 14px">Hello ' . $escape($request['full_name'] ?? 'Requester') . ',</p>'
                . '<p style="margin:0;color:#716b5d">Here is the latest information about your document request.</p>'
                . '</td></tr>'
                . '<tr><td style="padding:14px 20px;border-bottom:1px solid #e5d8b7">'
                . '<div style="font-size:11px;letter-spacing:1px;color:#927443;font-weight:bold">REQUEST PROGRESS</div>'
                . '<div style="margin-top:6px;font-size:17px;color:#302c25;font-weight:bold">' . $escape($label) . ' · ' . (int) $progress . '%</div>'
                . '<div style="margin-top:5px;color:#716b5d">Document: ' . $escape($request['document_type'] ?? '') . '</div>'
                . '</td></tr>' . $paymentBlock
                . (($request['status'] ?? '') === 'completed'
                    ? '<tr><td style="padding:16px 20px;border:1px solid #b9d1b5;background:#edf5e9">'
                        . '<div style="font-size:11px;letter-spacing:1px;color:#426b39;font-weight:bold">REQUEST COMPLETED</div>'
                        . '<div style="margin-top:7px;color:#2f6334;font-size:15px;font-weight:bold">Salamat sa pagtitiwala sa DocuQuest.</div>'
                        . '</td></tr>'
                    : '')
                . (($request['status'] ?? '') === 'ready_for_pickup'
                    ? '<tr><td style="padding:16px 20px;border:1px solid #b9d1b5;background:#edf5e9">'
                        . '<div style="font-size:11px;letter-spacing:1px;color:#426b39;font-weight:bold">READY FOR RELEASE</div>'
                        . '<div style="margin-top:7px;color:#2f6334;font-size:15px;font-weight:bold">Nakatakda ang pagkuha ng dokumento.</div>'
                        . '<div style="margin-top:5px;color:#4e6250;font-size:13px">' . $escape($release['label'] ?: 'Sundin ang iskedyul na ibinigay ng opisina.') . ' Philippine time. Dalhin ang iyong valid ID.</div>'
                        . '</td></tr>'
                    : '')
                . '<tr><td style="padding:16px 20px;border-bottom:1px solid #e5d8b7">'
                . '<div style="font-size:11px;letter-spacing:1px;color:#927443;font-weight:bold">OFFICE NOTE</div>'
                . '<div style="margin-top:6px;color:#4e493e;white-space:pre-line">' . $escape($note !== '' ? $note : 'No additional note provided.') . '</div>'
                . '</td></tr>'
                . (($payment && ($payment['status'] ?? '') === 'paid')
                    ? '<tr><td style="padding:16px 20px;border-bottom:1px solid #d8c58e;background:#edf5e9">'
                        . '<div style="font-size:11px;letter-spacing:1px;color:#557847;font-weight:bold">CONFIRMED PAYMENT</div>'
                        . '<div style="margin-top:7px;color:#2f6334;font-size:16px;font-weight:bold">Payment Receipt PDF attached</div>'
                        . '<div style="margin-top:5px;color:#716b5d;font-size:13px">Download the official payment receipt from the attachments in this email.</div>'
                        . '</td></tr>'
                    : '')
                . (($request['status'] ?? '') === 'completed' && trim((string) ($request['requirements'] ?? '')) !== ''
                    ? '<tr><td style="padding:16px 20px;border:1px solid #d8c58e;background:#fff9e8">'
                        . '<div style="font-size:11px;letter-spacing:1px;color:#927443;font-weight:bold">SUBMITTED REQUIREMENTS</div>'
                        . '<div style="margin-top:7px;color:#302c25;font-weight:bold">Verified requirements are listed below.</div>'
                        . '<div style="margin-top:5px;color:#716b5d;font-size:13px">The requester-uploaded requirement files are attached to this email.</div>'
                        . '</td></tr>'
                    : '')
                . (($certificate)
                    ? '<tr><td style="padding:16px 20px;border:1px solid #d8c58e;background:#fff9e8">'
                        . '<div style="font-size:11px;letter-spacing:1px;color:#927443;font-weight:bold">APPROVED REQUEST DOCUMENT</div>'
                        . '<div style="margin-top:7px;color:#302c25;font-size:16px;font-weight:bold">Approved Copy - Certified PDF</div>'
                        . '<div style="margin-top:5px;color:#716b5d;font-size:13px">The downloadable certified copy is attached to this email. You can also sign in to DocuQuest and download it from ATTACHED FILES.</div>'
                        . '</td></tr>'
                    : '')
                . '<tr><td style="padding:16px 20px;color:#716b5d;font-size:13px;line-height:1.6">'
                . '<strong style="color:#66501c">Requirements before release:</strong> ' . $escape(($request['requirements'] ?? '') !== '' ? $request['requirements'] : 'No additional requirements listed.') . '<br>'
                . '<strong style="color:#66501c">Scheduled release:</strong> ' . $escape(($request['status'] ?? '') === 'ready_for_pickup' ? ($release['label'] ?: 'Not scheduled yet.') : 'See the Ready for release notification for the pickup schedule.') . '<br>'
                . '<strong style="color:#66501c">Payment:</strong> ' . $escape($paymentText) . '<br>'
                . '<strong style="color:#66501c">Updated:</strong> ' . $escape($updatedAt) . ' (Asia/Manila)'
                . '</td></tr><tr><td align="center" style="padding:4px 20px 24px">'
                . '<a href="' . $escape($paymentUrl) . '" style="display:inline-block;padding:12px 20px;border-radius:4px;background:#c49b47;color:#302516;text-decoration:none;font-weight:bold">Open DocuQuest</a>'
                . '</td></tr><tr><td style="padding:14px 20px;background:#f6f1e5;text-align:center;color:#817968;font-size:11px">DOCUQUEST · DOCUMENT REQUEST PORTAL</td></tr>'
                . '</table></td></tr></table></body></html>';

            $email->Body = $html;
            $email->AltBody = "Hello " . ($request['full_name'] ?? 'Requester') . ",\n\n"
                . "Your DocuQuest request has an update.\n"
                . 'Request: ' . ($request['request_number'] ?? '') . "\n"
                . 'Document: ' . ($request['document_type'] ?? '') . "\n"
                . 'Status: ' . $label . ' (' . (int) $progress . "%)\n"
                . 'Payment: ' . $paymentText . "\n"
                . (($request['status'] ?? '') === 'completed' ? "Salamat sa pagtitiwala sa DocuQuest.\n" : '')
                . (($request['status'] ?? '') === 'ready_for_pickup' ? 'Scheduled pickup: ' . ($release['label'] ?: 'Not scheduled yet.') . " Philippine time. Please bring a valid ID.\n" : '')
                . (($payment && ($payment['status'] ?? '') === 'paid') ? "Payment receipt PDF: Attached to this email.\n" : '')
                . (($request['status'] ?? '') === 'completed' ? "Requester requirement documents: Attached to this email when provided.\n" : '')
                . 'Office note: ' . ($note !== '' ? $note : 'No additional note provided.') . "\n"
                . 'Requirements: ' . (($request['requirements'] ?? '') !== '' ? $request['requirements'] : 'No additional requirements listed.') . "\n"
                . ($certificate ? "Certified PDF: Download the Approved Copy - Certified PDF attached to this email, or download it from the request page.\n" : '')
                . 'Scheduled release: ' . (($request['status'] ?? '') === 'ready_for_pickup' ? ($release['label'] ?: 'Not scheduled yet.') : 'See the Ready for release notification for the pickup schedule.') . "\n"
                . 'Sign in: ' . $paymentUrl;
            $email->send();
            return true;
        } catch (Throwable $exception) {
            error_log('DocuQuest request update email failed: ' . $exception->getMessage());
            return false;
        }
    }

    protected function renderPaymentReceiptPdf(array $request, array $payment)
    {
        $escape = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $paidAt = $payment['paid_at'] ?? gmdate('Y-m-d H:i:s');
        try {
            $paid = new DateTimeImmutable($paidAt, new DateTimeZone('UTC'));
            $paid = $paid->setTimezone(new DateTimeZone('Asia/Manila'));
        } catch (Throwable $exception) {
            $paid = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        }
        $sealPath = ROOT_DIR . 'public/assets/b131edbc-193e-4b3e-99e9-aa8f1855c9ea.jpg';
        $seal = is_file($sealPath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($sealPath)) : '';
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:16mm 18mm 18mm}body{font-family:DejaVu Sans,sans-serif;color:#25231e;font-size:11px;line-height:1.35}.gov{border-top:5px solid #c79a1b;border-bottom:2px solid #263b31;padding:8px 0 7px;text-align:center;position:relative}.seal{width:62px;height:62px;position:absolute;left:5px;top:9px}.gov-line{font-size:7px;letter-spacing:1px;font-weight:bold;text-transform:uppercase}.gov h1{margin:3px 0 1px;font-size:17px;text-transform:uppercase}.office{font-size:10px}.receipt{margin-top:18px;border:2px solid #263b31;padding:16px}.receipt h2{margin:0 0 10px;text-align:center;text-transform:uppercase;letter-spacing:.8px}.paid{margin:10px 0 14px;padding:9px;border:1px solid #9ab88d;background:#edf5e9;color:#32613a;font-weight:bold;text-align:center}.amount{display:inline-block;margin-top:3px;font-size:25px;color:#263b31}.reference{background:#f8f5ea;border-left:4px solid #c79a1b;padding:10px;margin-bottom:12px}.reference small{display:block;color:#665d4a;font-size:8px;font-weight:bold;text-transform:uppercase}table{width:100%;border-collapse:collapse}td{border-bottom:1px solid #ddd6c8;padding:7px 4px}.label{width:36%;color:#665d4a;font-weight:bold}.footer{position:fixed;bottom:-11mm;left:0;right:0;border-top:1px solid #263b31;padding-top:6px;text-align:center;color:#666;font-size:8px}.footer-seal{width:32px;height:32px;vertical-align:middle;margin-right:7px}.original{font-weight:bold;color:#263b31}</style></head><body><div class="gov">'
            . ($seal ? '<img class="seal" src="' . $escape($seal) . '" alt="Municipal Seal">' : '') . '<div class="gov-line">Republic of the Philippines</div><h1>Municipality of Naujan</h1><div class="office">Office of the Municipal Secretary</div></div>
            <div class="receipt"><h2>Official Payment Receipt</h2><div class="paid">PAYMENT CONFIRMED<br><span class="amount">PHP ' . number_format((float) ($payment['amount'] ?? 0), 2) . '</span></div><div class="reference"><small>Document request reference</small><strong>' . $escape($request['request_number'] ?? '') . '</strong></div><table><tr><td class="label">Requester</td><td>' . $escape($request['full_name'] ?? '') . '</td></tr><tr><td class="label">Document</td><td>' . $escape($request['document_type'] ?? '') . '</td></tr><tr><td class="label">Payment method</td><td>' . $escape(strtoupper((string) ($payment['method'] ?? 'PayMongo'))) . '</td></tr><tr><td class="label">Payment reference</td><td>' . $escape($payment['reference_no'] ?? '') . '</td></tr><tr><td class="label">Paid on</td><td>' . $escape($paid->format('F j, Y, g:i A')) . '<br>Philippine Standard Time</td></tr></table></div><div class="footer">'
            . ($seal ? '<img class="footer-seal" src="' . $escape($seal) . '" alt="">' : '') . '<span class="original">ORIGINAL COPY ISSUED BY THE MUNICIPAL OFFICE</span><br>Municipality of Naujan · DocuQuest Official Payment Receipt<br>This receipt confirms payment recorded in the system.</div></body></html>';
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    

    

    

    

    

    

    

    

    

    

    public function getRequestPayment($requestId)
    {
        return $this->db->table('payment')
            ->select('id, request_id, user_id, amount, method, reference_no, status, paid_at, created_at')
            ->where('request_id', (int) $requestId)
            ->limit(1)
            ->get() ?: null;
    }

    public function isPaymentSatisfied(?array $payment)
    {
        return $payment !== null
            && ($payment['status'] === 'paid'
                || ($payment['status'] === 'waived'
                    && number_format((float) $payment['amount'], 2, '.', '') === '0.00'));
    }

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    

    protected function addUpdate($requestId, $actorId, $status, $note, $requirements = null, $releaseAt = null)
    {
        $this->db->table('request_updates')->insert([
            'request_id' => (int) $requestId,
            'actor_id' => (int) $actorId,
            'status' => $status,
            'note' => $note,
            'requirements' => $requirements,
            'release_at' => $releaseAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    protected function notifyAdmins($requestId, $title, $body)
    {
        foreach ($this->getAdminIds() as $adminId) {
            $this->createNotification($adminId, $requestId, $title, $body);
        }
    }

    protected function getAdminIds()
    {
        $admins = $this->db->table('users')
            ->select('id')
            ->where('role', 'admin')
            ->where('is_active', 1)
            ->get_all();

        return array_map(static function ($admin) {
            return (int) $admin['id'];
        }, $admins);
    }

    protected function createNotification($userId, $requestId, $title, $body)
    {
        $this->db->table('notifications')->insert([
            'user_id' => (int) $userId,
            'request_id' => (int) $requestId,
            'title' => $title,
            'body' => $body,
            'is_read' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
