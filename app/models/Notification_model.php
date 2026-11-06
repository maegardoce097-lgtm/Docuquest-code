<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Notification_model extends Request_model
{
    public function getNotifications($userId, $limit = 50)
    {
        $limit = max(1, min(100, (int) $limit));
        return $this->db->table('notifications n')
            ->select('n.id, n.request_id, r.request_number, n.title, n.body, n.is_read, n.created_at')
            ->left_join('document_requests r', 'r.id = n.request_id')
            ->where('n.user_id', (int) $userId)
            ->order_by('n.id', 'DESC')
            ->limit($limit)
            ->get_all();
    }

    public function countUnreadNotifications($userId)
    {
        return (int) $this->db->table('notifications')
            ->where('user_id', (int) $userId)
            ->where('is_read', 0)
            ->count();
    }

    public function markNotificationsRead($userId)
    {
        $this->db->table('notifications')
            ->where('user_id', (int) $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    public function updateNotificationReadState($notificationId, $userId, $isRead)
    {
        $notification = $this->db->table('notifications')
            ->select('id, is_read')
            ->where('id', (int) $notificationId)
            ->where('user_id', (int) $userId)
            ->limit(1)
            ->get();
        if (!$notification) {
            return false;
        }
        if ((int) $notification['is_read'] === ($isRead ? 1 : 0)) {
            return true;
        }

        $this->db->table('notifications')
            ->where('id', (int) $notificationId)
            ->where('user_id', (int) $userId)
            ->update(['is_read' => $isRead ? 1 : 0]);
        return true;
    }

    public function deleteNotification($notificationId, $userId)
    {
        return $this->db->table('notifications')
            ->where('id', (int) $notificationId)
            ->where('user_id', (int) $userId)
            ->delete() === 1;
    }

}
