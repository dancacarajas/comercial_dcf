<?php

declare(strict_types=1);

/**
 * Helper de variaveis de ambiente.
 *
 * Carrega um arquivo .env (se existir) na raiz do projeto e expoe a
 * funcao env() usada pelos arquivos de configuracao. Funciona em
 * hospedagem compartilhada sem dependencias externas.
 */

if (!function_exists('load_env')) {
    /**
     * Le um arquivo .env simples (CHAVE=valor) para dentro do ambiente.
     */
    function load_env(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignora comentarios e linhas sem '='
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);

            // Remove aspas envolventes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($name === '') {
                continue;
            }

            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

if (!function_exists('env')) {
    /**
     * Retorna uma variavel de ambiente com valor padrao.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => 'true',
            'false', '(false)' => 'false',
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
