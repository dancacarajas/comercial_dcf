<?php

declare(strict_types=1);

/**
 * Instala ou atualiza o codigo de conduta do captador externo.
 * Executar: docker exec dcc_app php /var/www/html/scripts/seed_captador_conduta_contract.php
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Services\ContractTemplateSeeder;

$id = ContractTemplateSeeder::upsertCaptadorCondutaDefault();
echo "Codigo de conduta do captador externo instalado/atualizado (id={$id}).\n";
