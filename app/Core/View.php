<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renderizador de views simples baseado em arquivos PHP.
 *
 * Suporta layout (template mestre) e passagem de dados. A saida deve
 * ser escapada nas proprias views usando o helper e() (security.php).
 */
final class View
{
    private static string $viewsPath = '';

    private static function basePath(): string
    {
        if (self::$viewsPath === '') {
            self::$viewsPath = dirname(__DIR__) . '/Views';
        }

        return self::$viewsPath;
    }

    /**
     * Renderiza uma view, opcionalmente dentro de um layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/admin'): string
    {
        $content = self::capture(self::resolve($view), $data);

        if ($layout !== null) {
            $data['content'] = $content;
            return self::capture(self::resolve($layout), $data);
        }

        return $content;
    }

    /**
     * Envia uma view diretamente para a saida.
     *
     * @param array<string, mixed> $data
     */
    public static function display(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        echo self::render($view, $data, $layout);
    }

    private static function resolve(string $view): string
    {
        // Normaliza "pasta.arquivo" ou "pasta/arquivo".
        $relative = str_replace(['.', '\\'], '/', $view);
        $file     = self::basePath() . '/' . $relative . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View nao encontrada: {$view}");
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
