<?php

declare(strict_types=1);

/**
 * Instala ou atualiza o modelo padrão de contrato captador externo.
 * Executar: docker exec dcc_app php /var/www/html/scripts/seed_captador_externo_contract.php
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Services\ContractTemplateSeeder;

$id = ContractTemplateSeeder::upsertCaptadorExternoDefault();
echo "Modelo captador externo padrão instalado/atualizado (id={$id}).\n";
