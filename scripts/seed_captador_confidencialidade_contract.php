<?php

declare(strict_types=1);

/**
 * Instala ou atualiza o termo de confidencialidade do captador externo.
 * Executar: docker exec dcc_app php /var/www/html/scripts/seed_captador_confidencialidade_contract.php
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Services\ContractTemplateSeeder;

$id = ContractTemplateSeeder::upsertCaptadorConfidencialidadeDefault();
echo "Termo de confidencialidade do captador externo instalado/atualizado (id={$id}).\n";
