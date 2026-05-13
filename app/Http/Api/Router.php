<?php

declare(strict_types=1);

namespace App\Http\Api;

/**
 * Tiny HTTP router for /api/v1.
 *
 * Mirrors App\Admin\Router but stays standalone -- the public API is a
 * different surface (Bearer auth instead of session), so we keep its
 * dispatching local to App\Http\Api\ and don't share state with the
 * admin router.
 *
 * Pattern grammar:
 *   - Literal segments match verbatim
 *   - `{name}`          matches `[^/]+` and is captured
 *   - `{name:...path}`  matches the remainder of the URL (one or more
 *                       slash-separated segments) and is captured. Used
 *                       for endpoints that accept a sub-path argument
 *                       like /url-meta/<path> or /assets/<path>.
 *
 * The first matching route wins. Routes are registered in priority
 * order by the caller; longer/more specific patterns first.
 *
 * Handlers receive an associative array of captured parameters in
 * declaration order. They are responsible for writing the response;
 * the Router never echoes anything itself.
 */
final class Router
{
    /**
     * @var list<array{method:string, pattern:string, handler:callable}>
     */
    private array $routes = [];

    private string $basePath;

    public function __construct(string $basePath = '/api/v1')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $pattern, callable $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): self
    {
        return $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): self
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): self
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $this->basePath . $pattern,
            'handler' => $handler,
        ];
        return $this;
    }

    /**
     * Resolve a method/path pair against the registered routes.
     *
     * Returns ['handler' => callable, 'params' => array<string, string>]
     * on match, or null when no route matched. Separate from dispatch()
     * so tests can exercise the matcher without going through SAPI.
     */
    public function match(string $method, string $path): ?array
    {
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $m)) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (!is_int($k)) {
                        // Path captures arrive URL-encoded; decode for
                        // the handler so /url-meta/foo%2Fbar reads as
                        // foo/bar without each controller doing it.
                        $params[$k] = urldecode($v);
                    }
                }
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }
        return null;
    }

    /**
     * Dispatch the current request. Calls $notFound() when no route
     * matches; the caller supplies how 404 should be rendered (the
     * API entry point emits JSON, not HTML).
     */
    public function dispatch(callable $notFound): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Strip trailing slash unless it's just "/" so "/health" and
        // "/health/" route the same.
        $uri = rtrim($uri, '/') ?: '/';

        $match = $this->match($method, $uri);
        if ($match === null) {
            $notFound();
            return;
        }

        ($match['handler'])($match['params']);
    }

    /**
     * Compile a route pattern to a PHP regex.
     *
     * `{name}`       -> `(?P<name>[^/]+)`
     * `{name:path}`  -> `(?P<name>.+)`     (captures slashes too)
     */
    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([a-zA-Z]+))?\}/',
            static function (array $m): string {
                $name = $m[1];
                $modifier = $m[2] ?? '';
                if ($modifier === 'path') {
                    return '(?P<' . $name . '>.+)';
                }
                return '(?P<' . $name . '>[^/]+)';
            },
            $pattern
        );
        return '#^' . $regex . '$#';
    }
}
