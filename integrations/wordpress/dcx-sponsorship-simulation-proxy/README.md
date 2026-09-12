# DCX Sponsorship Simulation Proxy (Etapa 22.1)

Plugin WordPress server-side: `POST /wp-json/dcx-crm/v1/sponsorship-simulation`

Normativo: `docs/integrations/ETAPA22_1_WP_PROXY_ARCHITECTURE_V1.md` v1.1 FROZEN  
Border: `docs/integrations/ETAPA22_WORDPRESS_CRM_BORDER_V1.md` v1.2 FROZEN  
CRM: `36fe5a2` intocado

## Secret (obrigatório)

```php
// wp-config.php
define('DCX_CRM_LEAD_ENDPOINT_SECRET', 'MESMO_VALOR_DO_LEAD_ENDPOINT_SECRET');
```

Nunca em `wp_options`. Endpoint CRM é constante de código.

## Options não secretas (opcional)

```php
update_option('dcx_ssp_settings', [
    'enabled' => '1',
    'trusted_proxies' => ['10.0.0.0/8'], // peers que podem enviar XFF
    'timeout_seconds' => 12,
]);
```

## Testes

```bash
php integrations/wordpress/dcx-sponsorship-simulation-proxy/tests/run_tests.php
```
