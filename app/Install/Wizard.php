<?php

declare(strict_types=1);

namespace App\Install;

/**
 * Three-step install orchestrator.
 *
 * Step 1 -- system check         GET  /install
 * Step 2 -- site + admin form    GET  /install/identity      (handles back-button reload)
 *                                POST /install/identity      (validate + advance)
 * Step 3 -- confirm + finish     GET  /install/finish
 *                                POST /install/finish        (write .env, config, seed, mark)
 * Step 4 -- success              GET  /install/success
 *
 * Session shape: $_SESSION['install_wizard'] = [
 *   'csrf'     => string,             // re-used across all steps
 *   'identity' => array<string,string>, // validated step-2 fields, no plaintext password
 *   'password' => string,             // held in session only between step 2 and 3
 * ];
 */
final class Wizard
{
    private const VIEW_DIR = '/themes/install/views/';
    private const LAYOUT   = '/themes/install/layout.php';

    private static function appVersion(): string
    {
        $path = dirname(__DIR__, 2) . '/VERSION';
        if (!is_file($path)) {
            throw new \RuntimeException('VERSION file missing at ' . $path);
        }
        return trim((string) file_get_contents($path));
    }

    public function __construct(
        private string $siteRoot,
        private ConfigWriter $writer,
        private Seeder $seeder
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['install_wizard'])) {
            $_SESSION['install_wizard'] = ['csrf' => bin2hex(random_bytes(32))];
        }
    }

    /**
     * Dispatch by URL action token.
     */
    public function handle(string $action, array $request): void
    {
        match ($action) {
            'check'         => $this->renderStep1(),
            'identity'      => $this->renderStep2(),
            'identity_post' => $this->handleStep2Post($request),
            'finish'        => $this->renderStep3(),
            'finish_post'   => $this->handleStep3Post($request),
            'success'       => $this->renderSuccess(),
            default         => $this->redirect('/install'),
        };
    }

    // -----------------------------------------------------------------------
    // Step 1 -- environment audit
    // -----------------------------------------------------------------------
    private function renderStep1(): void
    {
        $rows = SystemCheck::run($this->siteRoot);
        $this->render('step1-check', [
            'currentStep' => 1,
            'checks'      => $rows,
            'allPass'     => SystemCheck::allPass($rows),
        ]);
    }

    // -----------------------------------------------------------------------
    // Step 2 -- identity form
    // -----------------------------------------------------------------------
    private function renderStep2(array $errors = [], array $values = []): void
    {
        $values = $values ?: ($_SESSION['install_wizard']['identity'] ?? []);
        $this->render('step2-identity', [
            'currentStep' => 2,
            'errors'      => $errors,
            'values'      => $values + $this->defaultIdentity(),
            'timezones'   => \DateTimeZone::listIdentifiers(),
        ]);
    }

    private function handleStep2Post(array $request): void
    {
        if (!$this->validCsrf($request)) {
            $this->renderStep2(['_csrf' => 'Session expired -- please retry.']);
            return;
        }
        [$errors, $values] = $this->validateIdentity($request);
        if ($errors !== []) {
            $this->renderStep2($errors, $values);
            return;
        }
        // Stash validated values + plaintext password for step 3.
        $password = $values['admin_password'];
        unset($values['admin_password']);
        $_SESSION['install_wizard']['identity'] = $values;
        $_SESSION['install_wizard']['password'] = $password;
        $this->redirect('/install/finish');
    }

    // -----------------------------------------------------------------------
    // Step 3 -- confirm + write
    // -----------------------------------------------------------------------
    private function renderStep3(): void
    {
        if (empty($_SESSION['install_wizard']['identity'])) {
            $this->redirect('/install/identity');
            return;
        }
        $this->render('step3-finish', [
            'currentStep' => 3,
            'identity'    => $_SESSION['install_wizard']['identity'],
        ]);
    }

    private function handleStep3Post(array $request): void
    {
        if (!$this->validCsrf($request)) {
            $this->fatal('Session expired. Please restart at /install.');
            return;
        }
        if (!$this->underRateLimit()) {
            http_response_code(429);
            $this->fatal('Too many install attempts -- wait a minute and retry.');
            return;
        }
        $identity = $_SESSION['install_wizard']['identity'] ?? null;
        $password = $_SESSION['install_wizard']['password'] ?? null;
        if (!$identity || !$password) {
            $this->redirect('/install/identity');
            return;
        }

        $seedDemo = !empty($request['seed_demo']);

        try {
            $fields = $identity + [
                'admin_password' => $password,
                'client_ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            ];
            $this->writer->writeAppConfig();
            $this->writer->writeCategoriesConfig();
            $this->writer->writeEnv($fields);
            $copied = $seedDemo ? $this->seeder->run() : [];
            $this->writer->markInstalled(self::appVersion());
        } catch (\Throwable $e) {
            error_log('[install] write failure: ' . $e->getMessage());
            $this->fatal('Install failed: ' . $e->getMessage());
            return;
        }

        // Clear sensitive data + mark progress, then redirect.
        $_SESSION['install_wizard']['copied'] = $copied;
        unset($_SESSION['install_wizard']['password']);
        $this->redirect('/install/success');
    }

    // -----------------------------------------------------------------------
    // Step 4 -- success
    // -----------------------------------------------------------------------
    private function renderSuccess(): void
    {
        $copied = $_SESSION['install_wizard']['copied'] ?? [];
        $this->render('success', [
            'currentStep' => 4,
            'version'     => self::appVersion(),
            'copied'      => $copied,
            'admin_url'   => '/admin/login',
        ]);
        // Drop the wizard session AFTER rendering -- the layout pulls a CSRF
        // token via csrfToken() which reads from the session.
        unset($_SESSION['install_wizard']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @return array{0: array<string,string>, 1: array<string,string>} [errors, values] */
    private function validateIdentity(array $r): array
    {
        $errors = [];
        $values = [
            'site_name'        => trim((string)($r['site_name']        ?? '')),
            'site_url'         => trim((string)($r['site_url']         ?? '')),
            'site_description' => trim((string)($r['site_description'] ?? '')),
            'admin_email'      => trim((string)($r['admin_email']      ?? '')),
            'admin_username'   => trim((string)($r['admin_username']   ?? '')),
            'admin_password'   => (string)($r['admin_password']        ?? ''),
            'timezone'         => trim((string)($r['timezone']         ?? '')),
            'language'         => trim((string)($r['language']         ?? 'en')),
        ];

        if ($values['site_name'] === '' || strlen($values['site_name']) > 100) {
            $errors['site_name'] = 'Required, max 100 characters.';
        }
        if (!filter_var($values['site_url'], FILTER_VALIDATE_URL)) {
            $errors['site_url'] = 'Must be a full URL like http://localhost:8080 or https://example.com.';
        }
        if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Valid email required.';
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $values['admin_username'])) {
            $errors['admin_username'] = '3-32 characters: letters, digits, _ or -.';
        }
        if (strlen($values['admin_password']) < 8) {
            $errors['admin_password'] = 'At least 8 characters.';
        } elseif (!preg_match('/[a-zA-Z]/', $values['admin_password']) || !preg_match('/\d/', $values['admin_password'])) {
            $errors['admin_password'] = 'Mix letters and digits.';
        }
        if (!in_array($values['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Pick a valid timezone.';
        }
        if ($values['language'] === '') {
            $values['language'] = 'en';
        }

        return [$errors, $values];
    }

    /** Sensible defaults for the form on first render. */
    private function defaultIdentity(): array
    {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return [
            'site_name'        => '',
            'site_url'         => $proto . '://' . $host,
            'site_description' => '',
            'admin_email'      => '',
            'admin_username'   => 'admin',
            'admin_password'   => '',
            'timezone'         => date_default_timezone_get() ?: 'UTC',
            'language'         => 'en',
        ];
    }

    private function csrfToken(): string
    {
        return $_SESSION['install_wizard']['csrf'] ?? '';
    }

    private function validCsrf(array $request): bool
    {
        $token = (string)($request['_csrf'] ?? '');
        $expected = $_SESSION['install_wizard']['csrf'] ?? '';
        return $token !== '' && hash_equals($expected, $token);
    }

    /** Simple file-backed sliding-window: max 5 finish attempts/min/IP. */
    private function underRateLimit(): bool
    {
        $store = $this->siteRoot . '/storage/install-attempts.json';
        $now = time();
        $window = 60;
        $cap = 5;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!is_dir(dirname($store))) {
            mkdir(dirname($store), 0755, true);
        }

        $data = ['by_ip' => []];
        if (is_file($store)) {
            $raw = (string)file_get_contents($store);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded + $data;
            }
        }

        $hits = $data['by_ip'][$ip] ?? [];
        $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $now - $t < $window));

        if (count($hits) >= $cap) {
            $data['by_ip'][$ip] = $hits;
            file_put_contents($store, json_encode($data), LOCK_EX);
            return false;
        }

        $hits[] = $now;
        $data['by_ip'][$ip] = $hits;
        file_put_contents($store, json_encode($data), LOCK_EX);
        return true;
    }

    private function render(string $view, array $data): void
    {
        $viewFile = $this->siteRoot . self::VIEW_DIR . $view . '.php';
        $layoutFile = $this->siteRoot . self::LAYOUT;
        if (!is_file($viewFile) || !is_file($layoutFile)) {
            $this->fatal('Wizard theme missing: ' . $view);
            return;
        }
        $csrf = $this->csrfToken();
        extract($data, EXTR_SKIP);
        require $layoutFile;
    }

    private function fatal(string $message): void
    {
        $errorView = $this->siteRoot . self::VIEW_DIR . 'error.php';
        if (is_file($errorView)) {
            $layoutFile = $this->siteRoot . self::LAYOUT;
            $currentStep = 0;
            $viewFile = $errorView;
            require $layoutFile;
            return;
        }
        // Fallback if the theme file is missing too.
        header('Content-Type: text/plain; charset=utf-8');
        echo "Bird CMS install error\n\n" . $message . "\n";
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
    }
}
