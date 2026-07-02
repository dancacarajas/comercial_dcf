<?php

declare(strict_types=1);

/**
 * Instala ou atualiza o termo LGPD do captador externo.
 * Executar: docker exec dcc_app php /var/www/html/scripts/seed_captador_lgpd_contract.php
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Services\ContractTemplateSeeder;

$id = ContractTemplateSeeder::upsertCaptadorLgpdDefault();
echo "Termo LGPD do captador externo instalado/atualizado (id={$id}).\n";
