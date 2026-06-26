<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Roteador HTTP leve.
 *
 * Suporta metodos GET/POST, parametros nomeados ({id}) e middlewares.
 * Handlers podem ser: 'Controller@metodo' (namespace App\Controllers)
 * ou um callable (Closure).
 */
final class Router
{
    /** @var array<int, array{method:string,pattern:string,regex:string,handler:mixed,middleware:array<int,string>}> */
    private array $routes = [];

    private string $controllerNamespace = 'App\\Controllers\\';

    public function get(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => $this->compile($pattern),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Converte "/users/{id}" em regex com grupos nomeados.
     */
    private function compile(string $pattern): string
    {
        $pattern = '/' . trim($pattern, '/');
        $regex   = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $regex . '$#';
    }

    /**
     * Resolve a requisicao atual.
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path   = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) === 1) {
                $params = array_filter(
                    $matches,
                    static fn ($key) => !is_int($key),
                    ARRAY_FILTER_USE_KEY
                );

                $this->runMiddleware($route['middleware']);
                $this->invoke($route['handler'], $params);

                return;
            }
        }

        $this->notFound();
    }

    /**
     * @param array<int, string> $middleware
     */
    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $name) {
            $class = 'App\\Middlewares\\' . $name;

            if (!class_exists($class)) {
                throw new RuntimeException("Middleware nao encontrado: {$name}");
            }

            (new $class())->handle();
        }
    }

    /**
     * @param array<string, string> $params
     */
    private function invoke(mixed $handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($params);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $action] = explode('@', $handler, 2);
            $class = $this->controllerNamespace . $controller;

            if (!class_exists($class)) {
                throw new RuntimeException("Controller nao encontrado: {$class}");
            }

            $instance = new $class();

            if (!method_exists($instance, $action)) {
                throw new RuntimeException("Metodo nao encontrado: {$class}::{$action}");
            }

            $instance->{$action}($params);
            return;
        }

        throw new RuntimeException('Handler de rota invalido.');
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo View::render('errors/404', [], 'layouts/admin');
    }
}
