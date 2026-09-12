<?php
declare(strict_types=1);

/**
 * Bootstrap do harness 22.1 (sem WordPress).
 */
$root = dirname(__DIR__);

require_once $root . '/includes/class-dcx-ssp-constants.php';
require_once $root . '/includes/class-dcx-ssp-secrets.php';
require_once $root . '/includes/class-dcx-ssp-settings.php';
require_once $root . '/includes/class-dcx-ssp-client-ip.php';
require_once $root . '/includes/class-dcx-ssp-envelope-a.php';
require_once $root . '/includes/class-dcx-ssp-validator.php';
require_once $root . '/includes/class-dcx-ssp-envelope-b.php';
require_once $root . '/includes/class-dcx-ssp-crm-transport.php';
require_once $root . '/includes/class-dcx-ssp-envelope-c.php';
require_once $root . '/includes/class-dcx-ssp-logger.php';
require_once $root . '/includes/class-dcx-ssp-pipeline.php';
