<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

$router->get('/', 'Welcome::index');
$router->get('/healthz', 'Welcome::health');

// React application pages
$router->get('/how-to-use', 'Welcome::index');
$router->get('/login', 'Welcome::index');
$router->get('/register', 'Welcome::index');
$router->get('/dashboard', 'Welcome::index');
$router->get('/settings', 'Welcome::index');
$router->get('/admin', 'Welcome::index');
$router->get('/admin/login', 'Welcome::index');
$router->get('/admin/archive', 'Welcome::index');
$router->get('/admin/reports', 'Welcome::index');
$router->get('/admin/feedback', 'Welcome::index');
$router->get('/admin/files', 'Welcome::index');
$router->get('/admin/messages', 'Welcome::index');
$router->get('/messages', 'Welcome::index');
$router->get('/requests/show', 'Welcome::index');
$router->get('/notifications', 'Welcome::index');

// Authentication and account management
$router->post('/api/create', 'Auth_api::create');
$router->post('/api/login', 'Auth_api::login');
$router->post('/api/google-login', 'Auth_api::googleLogin');
$router->post('/api/logout', 'Auth_api::logout');
$router->post('/api/refresh', 'Auth_api::refresh');
$router->get('/api/profile', 'Auth_api::profile');
$router->post('/api/profile/update', 'Auth_api::updateProfile');

// Requester dashboard
$router->get('/api/dashboard', 'Request_api::dashboard');

// Public homepage content
$router->get('/api/homepage', 'Public_api::homepageContent');
$router->get('/api/homepage/image', 'Public_api::homepageImage');

// Admin homepage settings and accounts
$router->get('/api/admin/homepage', 'Admin_api::adminHomepageSettings');
$router->post('/api/admin/homepage', 'Admin_api::updateHomepageSettings');
$router->get('/api/admin/accounts', 'Admin_api::adminAccounts');
$router->post('/api/admin/accounts', 'Admin_api::createAdminAccount');

// Admin files, storage, archive, reports, and feedback
$router->get('/api/admin/files', 'Admin_api::adminFiles');
$router->get('/api/admin/storage-photos', 'Admin_api::adminStoragePhotos');
$router->get('/api/admin/storage-photo', 'Admin_api::adminStoragePhoto');
$router->get('/api/admin/archive', 'Admin_api::adminArchive');
$router->get('/api/admin/reports', 'Admin_api::adminReports');
$router->get('/api/admin/feedback', 'Admin_api::adminFeedback');
$router->post('/api/admin/reports/save', 'Admin_api::saveReport');
$router->get('/api/admin/reports/saved', 'Admin_api::savedReports');
$router->get('/api/admin/reports/saved/show', 'Admin_api::showSavedReport');

// Payment management and PayMongo
$router->post('/api/admin/requests/payment', 'Payment_api::setRequestPaymentAmount');
$router->post('/api/paymongo/webhook', 'Payment_api::paymongoWebhook');

// Request submission, details, files, status, and messages
$router->post('/api/requests/delete', 'Admin_api::deleteArchivedRequest');
$router->post('/api/requests', 'Request_api::createRequest');
$router->post('/api/requests/feedback', 'Request_api::submitFeedback');
$router->post('/api/requests/payment/checkout', 'Payment_api::createPaymentCheckout');
$router->get('/api/requests/payment/confirm', 'Payment_api::confirmPayment');
$router->get('/api/requests/show', 'Request_api::showRequest');
$router->get('/api/requests/file', 'Request_api::downloadFile');
$router->get('/api/requests/message-file', 'Request_api::downloadMessageFile');
$router->post('/api/requests/status', 'Request_api::updateStatus');
$router->post('/api/requests/requirements/upload', 'Request_api::uploadRequirement');
$router->post('/api/requests/requirements/verify', 'Request_api::verifyRequirement');
$router->post('/api/requests/requirements/review', 'Request_api::reviewRequirementUpload');
$router->get('/api/requests/status-feed', 'Request_api::statusFeed');
$router->get('/api/requests/messages', 'Request_api::messages');
$router->post('/api/requests/message', 'Request_api::sendMessage');
$router->get('/api/notifications', 'Notification_api::notifications');
$router->post('/api/notifications/read', 'Notification_api::markNotificationsRead');
$router->post('/api/notifications/delete', 'Notification_api::deleteNotification');