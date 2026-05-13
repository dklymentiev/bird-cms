<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Base controller for admin panel
 */
abstract class Controller
{
    protected Auth $auth;
    protected array $config;
    protected string $themePath;

    public function __construct()
    {
        $this->auth = new Auth();
        $this->config = \App\Support\Config::load('admin');
        $this->themePath = dirname(__DIR__, 2) . '/themes/admin';

        // Tag every content save that happens inside an admin request as
        // source=admin so the dashboard's Recent edits card attributes it
        // correctly. The flag is per-process static; admin requests are
        // single-shot so a later non-admin caller in the same process
        // (CLI test harness) would have to reset it -- not a real risk
        // for PHP-FPM, and tests reset it explicitly.
        \App\Support\EditLog::$context = 'admin';

        // IP restriction check for ALL admin routes
        $this->enforceIpRestriction();
    }

    /**
     * Enforce IP restriction - show 404 to unauthorized IPs
     * This hides the admin panel existence from unauthorized visitors
     */
    protected function enforceIpRestriction(): void
    {
        if (!$this->auth->isIpAllowed()) {
            http_response_code(404);

            // Try to render site's 404 page, fallback to plain text
            $themesPath = dirname(__DIR__, 2) . '/themes';
            $activeTheme = config('active_theme');
            $notFoundView = $activeTheme ? $themesPath . '/' . $activeTheme . '/views/404.php' : null;

            if ($notFoundView && file_exists($notFoundView)) {
                $theme = theme_manager();
                $theme->render('404', ['config' => config()]);
            } else {
                echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head>';
                echo '<body><h1>404 Not Found</h1><p>The requested URL was not found.</p></body></html>';
            }
            exit;
        }
    }

    /**
     * Render a view with layout
     *
     * @param string $view View name (e.g., 'dashboard', 'articles/index')
     * @param array<string, mixed> $data Data to pass to view
     */
    protected function render(string $view, array $data = []): void
    {
        $viewPath = $this->themePath . '/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        // Default data available in all views
        $data = array_merge([
            'auth' => $this->auth,
            'config' => $this->config,
            'currentPath' => $_SERVER['REQUEST_URI'] ?? '/admin',
        ], $data);

        // Extract data to variables
        extract($data);

        // Capture view content
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // Render with layout
        require $this->themePath . '/layout.php';
    }

    /**
     * Render a view without layout (for login page, etc.)
     *
     * @param string $view View name
     * @param array<string, mixed> $data Data to pass to view
     */
    protected function renderWithoutLayout(string $view, array $data = []): void
    {
        $viewPath = $this->themePath . '/views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data);
        require $viewPath;
    }

    /**
     * Redirect to a URL
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Return JSON response
     *
     * @param mixed $data
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get POST data
     *
     * @param string|null $key Specific key or null for all
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    protected function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET data
     *
     * @param string|null $key Specific key or null for all
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    protected function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Set flash message
     */
    protected function flash(string $type, string $message): void
    {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Get and clear flash messages
     *
     * @return array<array{type: string, message: string}>
     */
    protected function getFlash(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        $this->auth->requireAuth();
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): bool
    {
        $token = $this->post('_csrf') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    /**
     * Generate CSRF token
     */
    protected function generateCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Sanitize a slug for URLs
     */
    protected function sanitizeSlug(string $slug): string
    {
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Sanitize a filename
     */
    protected function sanitizeFilename(string $filename): string
    {
        $filename = strtolower($filename);
        $filename = preg_replace('/[^a-z0-9-_.]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        return trim($filename, '-');
    }

    /**
     * Sanitize a path segment (directory name)
     */
    protected function sanitizePathSegment(string $segment): string
    {
        $segment = str_replace(['..', '/', '\\', "\0"], '', $segment);
        $segment = strtolower($segment);
        $segment = preg_replace('/[^a-z0-9-_]/', '-', $segment);
        $segment = preg_replace('/-+/', '-', $segment);
        return trim($segment, '-');
    }

    /**
     * Export PHP array as formatted string
     */
    protected function exportArray(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= $spaces . '    ';

            if (is_string($key)) {
                $output .= "'" . addslashes($key) . "' => ";
            }

            if (is_array($value)) {
                $output .= $this->exportArray($value, $indent + 1);
            } elseif (is_bool($value)) {
                $output .= $value ? 'true' : 'false';
            } elseif (is_int($value) || is_float($value)) {
                $output .= $value;
            } elseif (is_null($value)) {
                $output .= 'null';
            } else {
                $output .= "'" . addslashes((string)$value) . "'";
            }

            $output .= ",\n";
        }

        $output .= $spaces . ']';
        return $output;
    }
}
