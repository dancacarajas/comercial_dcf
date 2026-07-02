<?php

declare(strict_types=1);

/**
 * Instala ou atualiza o termo de projeto, PRONAC, comissao e territorio do captador.
 * Executar: docker exec dcc_app php /var/www/html/scripts/seed_captador_projeto_comissao_contract.php
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new \App\Core\App($root))->boot();

use App\Services\ContractTemplateSeeder;

$id = ContractTemplateSeeder::upsertCaptadorProjetoComissaoDefault();
echo "Termo de projeto/comissao do captador externo instalado/atualizado (id={$id}).\n";
