<?php

declare(strict_types=1);

/**
 * Instala ou atualiza o termo de uso do portal do captador.
 * Executar: docker exec dcc_app php /var/www/html/scripts/seed_captador_portal_uso_contract.php
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Services\ContractTemplateSeeder;

$id = ContractTemplateSeeder::upsertCaptadorPortalUsoDefault();
echo "Termo de uso do portal do captador instalado/atualizado (id={$id}).\n";
