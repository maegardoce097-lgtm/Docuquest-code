<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth_model extends Docuquest_model
{
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_ATTEMPT_WINDOW_SECONDS = 900;
    private const LOGIN_LOCK_SECONDS = 1800;

    public function loginLockoutStatus($email)
    {
        $attempt = $this->db->table('login_attempts')
            ->select('locked_until')
            ->where('email_hash', $this->loginAttemptKey($email))
            ->limit(1)
            ->get();
        $retryAfter = 0;
        if ($attempt && $attempt['locked_until']) {
            $lockedUntil = new DateTimeImmutable($attempt['locked_until'], new DateTimeZone('UTC'));
            $retryAfter = max(0, $lockedUntil->getTimestamp() - time());
        }

        return ['locked' => $retryAfter > 0, 'retry_after' => $retryAfter];
    }

    public function recordFailedLoginAttempt($email)
    {
        $emailHash = $this->loginAttemptKey($email);
        for ($retry = 0; $retry < 5; $retry++) {
            $this->db->transaction();
            try {
                $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                $attempt = $this->db->table('login_attempts')
                    ->select('attempts, window_started_at, locked_until')
                    ->where('email_hash', $emailHash)
                    ->limit(1)
                    ->get();
                if (!$attempt) {
                    try {
                        $this->db->table('login_attempts')->insert([
                            'email_hash' => $emailHash,
                            'attempts' => 0,
                            'window_started_at' => $now->format('Y-m-d H:i:s'),
                            'locked_until' => null,
                        ]);
                    } catch (Throwable $exception) {
                        if ($retry === 4) {
                            throw $exception;
                        }
                        $this->db->roll_back();
                        continue;
                    }
                    $attempt = [
                        'attempts' => 0,
                        'window_started_at' => $now->format('Y-m-d H:i:s'),
                        'locked_until' => null,
                    ];
                }

                $lockedUntil = $attempt['locked_until']
                    ? new DateTimeImmutable($attempt['locked_until'], new DateTimeZone('UTC'))
                    : null;
                $retryAfter = $lockedUntil ? max(0, $lockedUntil->getTimestamp() - $now->getTimestamp()) : 0;
                if ($retryAfter > 0) {
                    $this->db->commit();
                    return ['locked' => true, 'retry_after' => $retryAfter];
                }

                $windowStartedAt = new DateTimeImmutable($attempt['window_started_at'], new DateTimeZone('UTC'));
                $windowExpired = $windowStartedAt < $now->modify('-' . self::LOGIN_ATTEMPT_WINDOW_SECONDS . ' seconds');
                $attempts = ($windowExpired ? 0 : (int) $attempt['attempts']) + 1;
                $locked = $attempts >= self::LOGIN_ATTEMPT_LIMIT;
                $update = $this->db->table('login_attempts')
                    ->where('email_hash', $emailHash)
                    ->where('attempts', (int) $attempt['attempts'])
                    ->where('window_started_at', $attempt['window_started_at']);
                if ($attempt['locked_until'] === null) {
                    $update->where_null('locked_until');
                } else {
                    $update->where('locked_until', $attempt['locked_until']);
                }
                $updated = $update->update([
                    'attempts' => $attempts,
                    'window_started_at' => $windowExpired ? $now->format('Y-m-d H:i:s') : $attempt['window_started_at'],
                    'locked_until' => $locked ? $now->modify('+' . self::LOGIN_LOCK_SECONDS . ' seconds')->format('Y-m-d H:i:s') : null,
                ]);
                if ($updated !== 1) {
                    $this->db->roll_back();
                    continue;
                }

                $this->db->commit();
                return [
                    'locked' => $locked,
                    'retry_after' => $locked ? self::LOGIN_LOCK_SECONDS : 0,
                    'attempts' => $attempts,
                ];
            } catch (Throwable $exception) {
                $this->db->roll_back();
                throw $exception;
            }
        }

        throw new RuntimeException('Failed login attempt could not be recorded after concurrent updates.');
    }

    public function clearLoginAttempts($email)
    {
        $this->db->table('login_attempts')->where('email_hash', $this->loginAttemptKey($email))->delete();
    }

    private function loginAttemptKey($email)
    {
        return hash('sha256', strtolower(trim((string) $email)));
    }

    public function createUser($username, $email, $passwordHash)
    {
        return (int) $this->db->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
            'role' => 'user',
            'is_active' => 1,
        ]);
    }

}
