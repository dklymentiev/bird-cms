<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Handle authentication routes
 */
final class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): void
    {
        // Already logged in? Redirect to dashboard
        if ($this->auth->check()) {
            $this->redirect('/admin');
        }

        $ip = $this->auth->getClientIp();
        $lockedOut = $this->auth->isLockedOut($ip);
        $lockoutRemaining = $lockedOut ? $this->auth->getLockoutRemaining($ip) : 0;

        $this->renderWithoutLayout('login', [
            'csrf' => $this->generateCsrf(),
            'error' => $this->getFlash(),
            'lockedOut' => $lockedOut,
            'lockoutRemaining' => $lockoutRemaining,
        ]);
    }

    /**
     * Process login attempt
     */
    public function login(): void
    {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/admin');
        }

        $username = trim($this->post('username', ''));
        $password = $this->post('password', '');

        if ($this->auth->attempt($username, $password)) {
            $this->flash('success', 'Welcome back!');
            $this->redirect('/admin');
        }

        $this->flash('error', 'Invalid credentials. Please try again.');
        $this->redirect('/admin');
    }

    /**
     * Show logout confirmation (for CSRF-protected logout)
     */
    public function showLogout(): void
    {
        $this->auth->logout();
        $this->redirect('/admin');
    }

    /**
     * Logout user (POST with CSRF)
     */
    public function logout(): void
    {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Invalid security token.');
            $this->redirect('/admin');
            return;
        }

        $this->auth->logout();
        $this->redirect('/admin');
    }
}
