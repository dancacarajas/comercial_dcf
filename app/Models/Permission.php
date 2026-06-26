<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de permissoes.
 */
final class Permission extends Model
{
    protected string $table = 'permissions';

    /**
     * Prefixos de permissoes RESERVADAS para modulos de CRM futuros.
     */
    private const RESERVED_PREFIXES = [
        'proposals', 'documents',
        'sponsors', 'counterparts', 'reports',
    ];

    /**
     * Lista todas as permissoes ordenadas por slug.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allOrdered(): array
    {
        return $this->query(
            'SELECT `id`, `name`, `slug`, `description` FROM `permissions` ORDER BY `slug` ASC'
        )->fetchAll();
    }

    /**
     * Indica se um slug e de modulo futuro (reservado) ou administrativo.
     */
    public static function isReserved(string $slug): bool
    {
        $prefix = explode('.', $slug)[0] ?? '';

        return in_array($prefix, self::RESERVED_PREFIXES, true);
    }

    /**
     * Rotulo do tipo da permissao.
     */
    public static function kind(string $slug): string
    {
        return self::isReserved($slug) ? 'Reservada (módulo futuro)' : 'Administrativa';
    }
}
