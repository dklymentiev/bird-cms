<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Simple router for admin panel
 */
final class Router
{
    /** @var array<string, array<string, array{0: string, 1: string}>> Routes indexed by path then method */
    private array $routes = [];

    private string $basePath;

    public function __construct(string $basePath = '/admin')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Register a GET route
     */
    public function get(string $path, string $controller, string $action): self
    {
        return $this->addRoute('GET', $path, $controller, $action);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, string $controller, string $action): self
    {
        return $this->addRoute('POST', $path, $controller, $action);
    }

    /**
     * Add route to registry
     */
    private function addRoute(string $method, string $path, string $controller, string $action): self
    {
        $fullPath = $this->basePath . $path;
        if (!isset($this->routes[$fullPath])) {
            $this->routes[$fullPath] = [];
        }
        $this->routes[$fullPath][$method] = [$controller, $action];
        return $this;
    }

    /**
     * Get all registered routes (for debugging)
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Dispatch the current request
     */
    public function dispatch(): void
    {
        $uri = $this->getRequestUri();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Try exact match first
        if (isset($this->routes[$uri][$method])) {
            $this->executeHandler($this->routes[$uri][$method]);
            return;
        }

        // Try pattern matching for routes with parameters
        foreach ($this->routes as $routePath => $methods) {
            if (!isset($methods[$method])) {
                continue;
            }

            $params = $this->matchRoute($routePath, $uri);
            if ($params !== null) {
                $this->executeHandler($methods[$method], $params);
                return;
            }
        }

        // No route found
        $this->notFound();
    }

    /**
     * Match route pattern against URI
     *
     * @return array<string, string>|null
     */
    private function matchRoute(string $pattern, string $uri): ?array
    {
        // Convert route pattern to regex
        // {param} becomes named capture group
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Filter out numeric keys
            return array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Get clean request URI
     */
    private function getRequestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = rtrim($uri, '/');

        // Handle /admin -> /admin (dashboard)
        if ($uri === $this->basePath || $uri === '') {
            return $this->basePath;
        }

        return $uri;
    }

    /**
     * Execute route handler
     *
     * @param array{0: string, 1: string} $handler [ControllerClass, actionMethod]
     * @param array<string, string> $params Route parameters
     */
    private function executeHandler(array $handler, array $params = []): void
    {
        [$controllerClass, $action] = $handler;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller not found: {$controllerClass}");
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            throw new \RuntimeException("Action not found: {$controllerClass}::{$action}");
        }

        $controller->$action(...array_values($params));
    }

    /**
     * Handle 404 not found
     */
    private function notFound(): void
    {
        http_response_code(404);
        echo '404 - Page not found';
        exit;
    }
}
