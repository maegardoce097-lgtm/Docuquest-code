<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Notification_api extends Docuquest_api
{
    public function __construct()
    {
        parent::__construct();
        $this->docuquest = $this->call->model('Notification_model');
    }

    public function notifications()
    {
        $user = $this->authenticatedUser();
        $this->api->respond([
            'notifications' => $this->docuquest->getNotifications((int) $user['id']),
            'unread_count' => $this->docuquest->countUnreadNotifications((int) $user['id']),
        ]);
    }

    public function markNotificationsRead()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $input = $this->api->body();
        if (array_key_exists('notification_id', $input)) {
            $notificationId = filter_var($input['notification_id'], FILTER_VALIDATE_INT);
            $isRead = filter_var($input['is_read'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($notificationId === false || $notificationId === null || $notificationId < 1 || $isRead === null) {
                $this->api->respond_error('Choose a notification and a valid read state.', 422);
            }
            if (!$this->docuquest->updateNotificationReadState((int) $notificationId, (int) $user['id'], $isRead)) {
                $this->api->respond_error('Notification not found.', 404);
            }
            $message = $isRead ? 'Notification marked as read.' : 'Notification marked as unread.';
        } else {
            $this->docuquest->markNotificationsRead((int) $user['id']);
            $message = 'Notifications marked as read.';
        }

        $this->api->respond([
            'message' => $message,
            'unread_count' => $this->docuquest->countUnreadNotifications((int) $user['id']),
        ]);
    }

    public function deleteNotification()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $input = $this->api->body();
        $notificationId = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
        if ($notificationId === false || $notificationId === null || $notificationId < 1) {
            $this->api->respond_error('Choose a valid notification.', 422);
        }
        if (!$this->docuquest->deleteNotification((int) $notificationId, (int) $user['id'])) {
            $this->api->respond_error('Notification not found.', 404);
        }

        $this->api->respond([
            'message' => 'Notification deleted.',
            'unread_count' => $this->docuquest->countUnreadNotifications((int) $user['id']),
        ]);
    }

}
