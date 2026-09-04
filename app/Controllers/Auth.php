<?php

namespace App\Controllers;

use App\Models\PasswordResetModel;
use App\Models\UserModel;
use CodeIgniter\Controller;

class Auth extends Controller
{
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;

    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to('/dashboard');
        }
        return view('auth/login');
    }

    public function doLogin()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $userModel = new UserModel();
        $user = $userModel->where('username', $username)->first();

        $authenticated = false;

        if ($user) {
            if (password_verify((string) $password, (string) $user['password'])) {
                $authenticated = true;
            } elseif (hash_equals((string) $user['password'], (string) $password)) {
                // Legacy plaintext row: accept once, then transparently upgrade to a proper hash.
                $authenticated = true;
                $userModel->update($user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            }
        }

        if ($authenticated) {
            session()->set([
                'logged_in' => true,
                'user_id'   => $user['id'],
                'username'  => $user['username'],
            ]);
            return redirect()->to('/dashboard');
        }

        return redirect()->to('/login')->with('error', 'Invalid username or password.');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }

    // =========================================================
    // FORGOT PASSWORD — Step 1: request OTP by email
    // =========================================================

    public function forgotPassword()
    {
        return view('auth/forgot_password');
    }

    public function sendOtp()
    {
        $email = trim((string) $this->request->getPost('email'));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid email address.');
        }

        $userModel = new UserModel();
        $user = $userModel->findByEmail($email);

        session()->set([
            'reset_email'     => $email,
            'reset_user_id'   => $user['id'] ?? null,
            'reset_verified'  => false,
            'reset_last_sent' => time(),
        ]);

        if ($user) {
            $resetModel = new PasswordResetModel();
            $otp = $resetModel->createOtp((int) $user['id'], $email);

            // ---- TEMP DEBUG (Release 4.7.0 SMTP troubleshooting) ----
            $emailService = service('email');
            $emailService->setTo($email);
            $emailService->setSubject('A&A Inventory ERP - Password Reset Verification Code');
            $emailService->setMailType('html');
            $emailService->setMessage(view('emails/otp_email', ['otp' => $otp]));

            if (! $emailService->send()) {
                echo '<pre>';
                print_r($emailService->printDebugger(['headers', 'subject', 'body']));
                exit;
            }
            // ---- END TEMP DEBUG ----
        }

        return redirect()->to('/forgot-password/verify')
            ->with('success', 'If the email exists in our system, a verification code has been sent.');
    }

    // =========================================================
    // FORGOT PASSWORD — Step 2: verify OTP
    // =========================================================

    public function verifyOtp()
    {
        if (! session()->get('reset_email')) {
            return redirect()->to('/forgot-password');
        }

        if ($this->request->is('post')) {
            $otp = trim((string) $this->request->getPost('otp'));

            if ($otp === '' || ! preg_match('/^\d{6}$/', $otp)) {
                return redirect()->back()->with('error', 'Please enter the 6-digit verification code.');
            }

            $userId = session()->get('reset_user_id');
            $resetModel = new PasswordResetModel();
            $row = $userId ? $resetModel->getLatestForUser((int) $userId) : null;

            if (! $row) {
                return redirect()->back()->with('error', 'Invalid or expired verification code.');
            }

            if ($resetModel->isExpired($row)) {
                return redirect()->back()->with('error', 'This verification code has expired. Please request a new one.');
            }

            if (! hash_equals($row['otp_code'], $otp)) {
                return redirect()->back()->with('error', 'Invalid verification code.');
            }

            $resetModel->markVerified($row['id']);
            session()->set('reset_verified', true);

            return redirect()->to('/forgot-password/reset');
        }

        return view('auth/verify_otp', [
            'email'          => session()->get('reset_email'),
            'resendCooldown' => self::OTP_RESEND_COOLDOWN_SECONDS,
            'lastSent'       => session()->get('reset_last_sent'),
        ]);
    }

    public function resendOtp()
    {
        if (! session()->get('reset_email')) {
            return redirect()->to('/forgot-password');
        }

        $lastSent = (int) session()->get('reset_last_sent');
        $elapsed = time() - $lastSent;

        if ($elapsed < self::OTP_RESEND_COOLDOWN_SECONDS) {
            $wait = self::OTP_RESEND_COOLDOWN_SECONDS - $elapsed;
            return redirect()->to('/forgot-password/verify')->with('error', "Please wait {$wait} seconds before requesting another code.");
        }

        $userId = session()->get('reset_user_id');
        $email = session()->get('reset_email');

        if ($userId) {
            $resetModel = new PasswordResetModel();
            $otp = $resetModel->createOtp((int) $userId, $email);
            $this->sendOtpEmail($email, $otp);
        }

        session()->set('reset_last_sent', time());

        return redirect()->to('/forgot-password/verify')
            ->with('success', 'If the email exists in our system, a new verification code has been sent.');
    }

    // =========================================================
    // FORGOT PASSWORD — Step 3: set new password
    // =========================================================

    public function resetPassword()
    {
        if (! session()->get('reset_verified') || ! session()->get('reset_user_id')) {
            return redirect()->to('/forgot-password');
        }

        if ($this->request->is('post')) {
            $password = (string) $this->request->getPost('password');
            $confirm  = (string) $this->request->getPost('confirm_password');

            if (strlen($password) < 8
                || ! preg_match('/[A-Z]/', $password)
                || ! preg_match('/[a-z]/', $password)
                || ! preg_match('/[0-9]/', $password)
            ) {
                return redirect()->back()->with('error', 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number.');
            }

            if ($password !== $confirm) {
                return redirect()->back()->with('error', 'Passwords do not match.');
            }

            $userId = (int) session()->get('reset_user_id');
            $userModel = new UserModel();
            $userModel->update($userId, ['password' => password_hash($password, PASSWORD_DEFAULT)]);

            $resetModel = new PasswordResetModel();
            $resetModel->deleteForUser($userId);

            session()->remove(['reset_email', 'reset_user_id', 'reset_verified', 'reset_last_sent']);

            return redirect()->to('/login')->with('success', 'Password updated successfully. Please login with your new password.');
        }

        return view('auth/reset_password');
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function sendOtpEmail(string $email, string $otp): void
    {
        $emailService = service('email');
        $emailService->setTo($email);
        $emailService->setSubject('A&A Inventory ERP - Password Reset Verification Code');
        $emailService->setMailType('html');
        $emailService->setMessage(view('emails/otp_email', ['otp' => $otp]));
        $emailService->send();
    }
}
