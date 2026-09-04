<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table         = 'password_resets';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['user_id', 'email', 'otp_code', 'expires_at', 'verified_at'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    private const OTP_TTL_MINUTES = 10;

    /**
     * Deletes any existing OTP for the user and creates a fresh one.
     * Only one active OTP per user is ever stored.
     */
    public function createOtp(int $userId, string $email): string
    {
        $this->where('user_id', $userId)->delete();

        $otp = (string) random_int(100000, 999999);

        $this->insert([
            'user_id'    => $userId,
            'email'      => $email,
            'otp_code'   => $otp,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::OTP_TTL_MINUTES . ' minutes')),
        ]);

        return $otp;
    }

    public function getLatestForUser(int $userId): ?array
    {
        $row = $this->where('user_id', $userId)
                     ->orderBy('id', 'DESC')
                     ->first();

        return $row ?: null;
    }

    public function isExpired(array $resetRow): bool
    {
        return strtotime($resetRow['expires_at']) < time();
    }

    public function markVerified(int $id): void
    {
        $this->update($id, ['verified_at' => date('Y-m-d H:i:s')]);
    }

    public function deleteForUser(int $userId): void
    {
        $this->where('user_id', $userId)->delete();
    }
}
