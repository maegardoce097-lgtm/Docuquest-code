<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Admin_model extends Request_model
{
    public function listAdminAccounts()
    {
        return $this->db->table('users')
            ->select('id, username, email, is_active, created_at')
            ->where('role', 'admin')
            ->order_by('username', 'ASC')
            ->get_all();
    }

    public function createAdminUser($username, $email, $passwordHash)
    {
        return (int) $this->db->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
            'role' => 'admin',
            'is_active' => 1,
        ]);
    }

    public function getAllRequestFeedback()
    {
        $feedback = $this->db->table('request_feedback f')
            ->select('f.id, f.request_id, f.user_id, f.rating, f.staff_service_rating, f.instructions_clarity_rating, f.status_updates_rating, f.processing_time_rating, f.overall_satisfaction_rating, f.comment, f.survey_json, f.created_at, r.request_number, r.document_type, r.full_name, u.username')
            ->inner_join('document_requests r', 'r.id = f.request_id')
            ->inner_join('users u', 'u.id = f.user_id')
            ->order_by('f.created_at', 'DESC')
            ->get_all();
        foreach ($feedback as &$entry) {
            $entry = $this->decodeFeedbackSurvey($entry);
        }
        unset($entry);
        return $feedback;
    }

    public function saveRegistryReport($start, $end, array $requests, $adminId)
    {
        $reportNumber = 'RPT-' . str_replace('-', '', $start) . '-' . str_replace('-', '', $end) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $snapshot = json_encode(['requests' => $requests, 'saved_at' => gmdate('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($snapshot === false) throw new RuntimeException('The report snapshot could not be prepared.');
        $this->db->table('saved_registry_reports')->insert([
            'report_number' => $reportNumber,
            'start_date' => $start,
            'end_date' => $end,
            'record_count' => count($requests),
            'snapshot_json' => $snapshot,
            'created_by' => (int) $adminId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $reportNumber;
    }

    public function listSavedRegistryReports()
    {
        return $this->db->table('saved_registry_reports r')
            ->select('r.id, r.report_number, r.start_date, r.end_date, r.record_count, r.created_at, u.username AS created_by_name')
            ->inner_join('users u', 'u.id = r.created_by')
            ->order_by('r.id', 'DESC')
            ->limit(100)
            ->get_all();
    }

    public function getSavedRegistryReport($reportId)
    {
        $report = $this->db->table('saved_registry_reports r')
            ->select('r.id, r.report_number, r.start_date, r.end_date, r.record_count, r.snapshot_json, r.created_at, u.username AS created_by_name')
            ->inner_join('users u', 'u.id = r.created_by')
            ->where('r.id', (int) $reportId)
            ->limit(1)
            ->get();
        if (!$report) return null;
        $snapshot = json_decode((string) $report['snapshot_json'], true);
        $report['requests'] = is_array($snapshot['requests'] ?? null) ? $snapshot['requests'] : [];
        unset($report['snapshot_json']);
        return $report;
    }

    public function getReportRequests($start, $end)
    {
        $startDay = $this->manilaDayBounds($start);
        $endDay = $this->manilaDayBounds($end);
        if (!$startDay || !$endDay || $startDay['start_utc'] > $endDay['start_utc']) return [];
        $endExclusive = (new DateTimeImmutable($endDay['end_utc'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $requests = $this->db->table('document_requests r')
            ->select($this->requestListColumns() . ', u.username AS requester_name, u.email AS requester_email, p.amount AS payment_amount, p.status AS payment_status, p.paid_at')
            ->inner_join('users u', 'u.id = r.user_id')
            ->left_join('payment p', 'p.request_id = r.id')
            ->where('r.created_at', '>=', $startDay['start_utc'])
            ->where('r.created_at', '<', $endExclusive)
            ->get_all();
        usort($requests, static function ($left, $right) {
            return [$right['created_at'], (int) $right['id']] <=> [$left['created_at'], (int) $left['id']];
        });
        return $this->addManilaSubmissionTimes($requests);
    }

    public function getAdminFiles($search = '')
    {
        $requestQuery = $this->db->table('request_files f')
            ->select('f.id, f.original_name, f.mime_type, f.size_bytes, f.created_at, r.id AS request_id, r.request_number, r.full_name AS requester_name, u.username AS requester_username, u.email AS requester_email, r.document_type')
            ->inner_join('document_requests r', 'r.id = f.request_id')
            ->inner_join('users u', 'u.id = r.user_id');
        $messageQuery = $this->db->table('request_message_files f')
            ->select('f.id, f.original_name, f.mime_type, f.size_bytes, f.created_at, r.id AS request_id, r.request_number, r.full_name AS requester_name, u.username AS requester_username, u.email AS requester_email, r.document_type')
            ->inner_join('request_messages m', 'm.id = f.message_id')
            ->inner_join('document_requests r', 'r.id = m.request_id')
            ->inner_join('users u', 'u.id = r.user_id');

        if ($search !== '') {
            $term = '%' . $search . '%';
            $filter = static function ($query) use ($term) {
                $query->like('r.full_name', $term)
                    ->or_like('u.username', $term)
                    ->or_like('u.email', $term)
                    ->or_like('r.request_number', $term)
                    ->or_like('r.document_type', $term)
                    ->or_like('f.original_name', $term);
            };
            $requestQuery->grouped($filter);
            $messageQuery->grouped($filter);
        }

        $requestFiles = $requestQuery->get_all();
        foreach ($requestFiles as &$file) {
            $file['file_source'] = 'request';
        }
        unset($file);

        $messageFiles = $messageQuery->get_all();
        foreach ($messageFiles as &$file) {
            $file['file_source'] = 'message';
        }
        unset($file);

        $files = array_merge($requestFiles, $messageFiles);
        usort($files, static function ($left, $right) {
            return [$right['created_at'], (int) $right['id']] <=> [$left['created_at'], (int) $left['id']];
        });

        return $files;
    }

    public function getStoragePhotoMetadata(array $storedNames)
    {
        $storedNames = array_values(array_filter($storedNames, static function ($name) {
            return is_string($name) && $name !== '';
        }));
        if (!$storedNames) {
            return [];
        }

        $requestFiles = $this->db->table('request_files f')
            ->select('f.stored_name AS storage_key, f.original_name, f.created_at, u.username AS sender_name, u.role AS sender_role, r.request_number')
            ->inner_join('users u', 'u.id = f.uploaded_by')
            ->inner_join('document_requests r', 'r.id = f.request_id')
            ->in('f.stored_name', $storedNames)
            ->get_all();
        $messageFiles = $this->db->table('request_message_files f')
            ->select('f.stored_name AS storage_key, f.original_name, m.created_at, u.username AS sender_name, u.role AS sender_role, r.request_number')
            ->inner_join('request_messages m', 'm.id = f.message_id')
            ->inner_join('users u', 'u.id = m.sender_id')
            ->inner_join('document_requests r', 'r.id = m.request_id')
            ->in('f.stored_name', $storedNames)
            ->get_all();

        $metadata = [];
        foreach ($requestFiles as $file) {
            $file['source_type'] = 'Request document';
            $metadata[$file['storage_key']] = $file;
        }
        foreach ($messageFiles as $file) {
            $file['source_type'] = 'Message image';
            $metadata[$file['storage_key']] = $file;
        }

        return $metadata;
    }

}
