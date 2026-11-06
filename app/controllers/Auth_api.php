<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth_api extends Docuquest_api
{
    public function __construct()
    {
        parent::__construct();
        $this->docuquest = $this->call->model('Auth_model');
    }

    public function create()
    {
        $this->api->require_method('POST');
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
        if ($confirmation !== '' && $password !== $confirmation) {
            $this->api->respond_error('Password confirmation does not match.', 422);
        }
        if ($this->docuquest->findUserByEmail($email)) {
            $this->api->respond_error('An account with this email already exists.', 409);
        }
        if ($this->docuquest->findUserByUsername($username)) {
            $this->api->respond_error('An account with this name already exists. Please use another name.', 409);
        }

        $userId = $this->docuquest->createUser($username, $email, password_hash($password, PASSWORD_DEFAULT));
        $user = $this->docuquest->findUserById($userId);
        $tokens = $this->issueTokens($user);
        $this->api->respond(['message' => 'Account created successfully.', 'user' => $user, 'tokens' => $tokens], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();
        $email = strtolower(trim($this->stringValue($input, 'email')));
        $password = $this->stringValue($input, 'password');
        if (!$this->verifyRecaptchaToken($this->stringValue($input, 'recaptcha_token'), 'login')) {
            $this->api->respond_error('Security verification failed. Please try again.', 403);
        }

        $lockout = $this->docuquest->loginLockoutStatus($email);
        if ($lockout['locked']) {
            header('Retry-After: ' . $lockout['retry_after']);
            $this->api->respond_error('Too many failed login attempts. Try again in 30 minutes.', 429);
        }

        $account = $this->docuquest->findUserByEmail($email);

        if (!$account || (int) $account['is_active'] !== 1 || !password_verify($password, $account['password'])) {
            $lockout = $this->docuquest->recordFailedLoginAttempt($email);
            if ($lockout['locked']) {
                header('Retry-After: ' . $lockout['retry_after']);
                $this->api->respond_error('Too many failed login attempts. Try again in 30 minutes.', 429);
            }
            $this->api->respond_error('The email or password is incorrect, or this account is inactive.', 401);
        }

        $this->docuquest->clearLoginAttempts($email);
        $user = $this->docuquest->findUserById((int) $account['id']);
        $this->api->respond([
            'message' => 'Signed in successfully.',
            'user' => $user,
            'tokens' => $this->issueTokens($user),
        ]);
    }

    public function googleLogin()
    {
        $this->api->require_method('POST');
        $clientId = trim((string) getenv('GOOGLE_CLIENT_ID'));
        if ($clientId === '') {
            $this->api->respond_error('Google sign-in is not configured.', 503);
        }

        $input = $this->api->body();
        if (!$this->verifyRecaptchaToken($this->stringValue($input, 'recaptcha_token'), 'google_login')) {
            $this->api->respond_error('Security verification failed. Please try again.', 403);
        }

        $credential = $this->stringValue($input, 'credential');
        $claims = $this->verifyGoogleCredential($credential, $clientId);
        if (!$claims) {
            $this->api->respond_error('Google sign-in could not be verified. Please try again.', 401);
        }

        $email = strtolower(trim($claims['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->api->respond_error('Google did not provide a valid email address.', 401);
        }

        $account = $this->docuquest->findUserByEmail($email);
        if ($account && ((int) $account['is_active'] !== 1 || $account['role'] !== 'user')) {
            $this->api->respond_error('Google sign-in is only available for active requester accounts.', 403);
        }

        if (!$account) {
            $username = trim(preg_replace('/\\s+/', ' ', (string) ($claims['name'] ?? '')));
            if ($username === '') {
                $username = strstr($email, '@', true) ?: 'Google user';
            }
            $username = mb_substr($username, 0, 100, 'UTF-8');
            if (mb_strlen($username, 'UTF-8') < 2) {
                $username = 'Google user';
            }
            while ($this->docuquest->findUserByUsername($username)) {
                $username = mb_substr($username, 0, 88, 'UTF-8') . ' ' . bin2hex(random_bytes(4));
            }

            $userId = $this->docuquest->createUser(
                $username,
                $email,
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)
            );
            $account = $this->docuquest->findUserByEmail($email);
        }

        $user = $this->docuquest->findUserById((int) $account['id']);
        if (!$user || $user['role'] !== 'user') {
            $this->api->respond_error('This account is not available for Google sign-in.', 403);
        }

        $this->api->respond([
            'message' => 'Signed in with Google successfully.',
            'user' => $user,
            'tokens' => $this->issueTokens($user),
        ]);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $this->authenticatedUser();
        $input = $this->api->body();
        $refreshToken = $this->stringValue($input, 'refresh_token');
        if ($refreshToken !== '') {
            $this->api->revoke_refresh_token($refreshToken);
        }
        $this->api->respond(['message' => 'Signed out successfully.']);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();
        $this->api->refresh_access_token($this->stringValue($input, 'refresh_token'));
    }

    public function profile()
    {
        $user = $this->authenticatedUser();
        $this->api->respond(['user' => $user]);
    }

    public function updateProfile()
    {
        $this->api->require_method('POST');
        $user = $this->authenticatedUser();
        $input = $this->api->body();
        $username = trim($this->stringValue($input, 'username'));
        $email = strtolower(trim($this->stringValue($input, 'email')));
        $currentPassword = $this->stringValue($input, 'current_password');
        $newPassword = $this->stringValue($input, 'new_password');
        $confirmPassword = $this->stringValue($input, 'confirm_password');

        if (strlen($username) < 2 || strlen($username) > 100) {
            $this->api->respond_error('Name must be between 2 and 100 characters.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $this->api->respond_error('Enter a valid email address.', 422);
        }

        $passwordHash = null;
        if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
            $account = $this->docuquest->getUserRecordForUpdate((int) $user['id']);
            if (!$account || !password_verify($currentPassword, $account['password'])) {
                $this->api->respond_error('Your current password is incorrect.', 401);
            }
            if (strlen($newPassword) < 10) {
                $this->api->respond_error('New password must be at least 10 characters long.', 422);
            }
            if ($newPassword !== $confirmPassword) {
                $this->api->respond_error('The new password confirmation does not match.', 422);
            }
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        try {
            $updatedUser = $this->docuquest->updateUserProfile((int) $user['id'], $username, $email, $passwordHash);
        } catch (RuntimeException $exception) {
            $this->api->respond_error($exception->getMessage(), 409);
        }

        $this->api->respond([
            'message' => 'Your account details were updated.',
            'user' => $updatedUser,
        ]);
    }

    private function issueTokens(array $user)
    {
        $scopes = $user['role'] === 'admin' ? ['read', 'write', 'delete'] : ['read', 'write'];
        return $this->api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'scopes' => $scopes,
        ]);
    }

    private function verifyRecaptchaToken($token, $expectedAction)
    {
        $environment = strtolower(trim((string) getenv('APP_ENV')));
        if (in_array($environment, ['development', 'local', 'dev', 'testing'], true)) {
            return true;
        }

        $secret = trim((string) getenv('RECAPTCHA_SECRET_KEY'));
        if ($secret === '' || $token === '' || strlen($token) > 4096) {
            return false;
        }

        $handle = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $token,
            ]),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_string($response) || $status !== 200) {
            return false;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            return false;
        }

        $requestHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $expectedHost = strtolower((string) parse_url('http://' . $requestHost, PHP_URL_HOST));
        $hostname = strtolower((string) ($result['hostname'] ?? ''));
        $configuredScore = getenv('RECAPTCHA_MIN_SCORE');
        $minimumScore = is_numeric($configuredScore) ? max(0, min(1, (float) $configuredScore)) : 0.5;

        return ($result['success'] ?? false) === true
            && ($result['action'] ?? '') === $expectedAction
            && $expectedHost !== ''
            && hash_equals($expectedHost, $hostname)
            && is_numeric($result['score'] ?? null)
            && (float) $result['score'] >= $minimumScore;
    }

    private function verifyGoogleCredential($credential, $clientId)
    {
        if ($credential === '' || strlen($credential) > 10000) {
            return null;
        }

        $handle = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_string($response) || $status !== 200) {
            return null;
        }

        $claims = json_decode($response, true);
        if (!is_array($claims)) {
            return null;
        }

        $audience = $claims['aud'] ?? null;
        $audienceMatches = is_string($audience) && hash_equals($clientId, $audience);
        if (is_array($audience)) {
            $audienceMatches = in_array($clientId, $audience, true);
        }
        $issuer = $claims['iss'] ?? '';
        $emailVerified = ($claims['email_verified'] ?? false) === true
            || ($claims['email_verified'] ?? false) === 'true';

        if (!$audienceMatches
            || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || !$emailVerified
            || empty($claims['email'])
            || empty($claims['sub'])
            || (int) ($claims['exp'] ?? 0) <= time()) {
            return null;
        }

        return $claims;
    }

}
