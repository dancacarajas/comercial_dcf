# ETAPA 22.1 — Arquitetura interna e contrato de testes do proxy WP

**Status:** DESENHO **v1.1 — FROZEN**  
**Normativo:** `ETAPA22_WORDPRESS_CRM_BORDER_V1.md` v1.2 FROZEN (`d58e69b`)  
**Escopo:** `ETAPA22_1_WP_PROXY_SCOPE.md` v1.1 FROZEN  
**CRM:** `36fe5a2` INTOCADO  

```text
DOCUMENT FREEZE ........ PASS / FROZEN
CÓDIGO ................ AUTORIZADO
```

---

## 0. Delta v1.1 (blockers fechados)

| # | Blocker | Resolução |
|---|---|---|
| 1 | `crm_endpoint` em `wp_options` | Literal/constante de código; imutável em V1 |
| 2 | “byte-a-byte” identidade/UTMs | Preservar sem regenerar snapshot; canonicalizar campos border uma vez |
| 3 | Cadeia XFF ambígua | Algoritmo trusted-proxy direita→esquerda + T44–T47 |
| 4 | HTTP 201 CRM malformado → falso RECEIVED | Aceitar 201 só com `success===true` e `idempotent` boolean + T66–T69 |
| 5 | Honeypot omite UUID (4º shape) | Validar UUID antes do honeypot; C sempre com `submission_uuid` |

---

## 1. Packaging

| Opção | Veredito |
|---|---|
| Arquivo solto no tema | **REJEITADO** |
| Dependência de tema | **REJEITADO** |
| **Plugin próprio** `integrations/wordpress/dcx-sponsorship-simulation-proxy/` | **ADOTADO** |

```text
POST /wp-json/dcx-crm/v1/sponsorship-simulation
```

Rota **pública/anonymous**. Proteção do CRM = secret server-side no salto WP→CRM.  
Nonce WP / sessão / capability **não** protegem o token.

JS (fora desta etapa) recebe **somente** a URL do proxy.

---

## 2. Layout

```text
integrations/wordpress/dcx-sponsorship-simulation-proxy/
├── dcx-sponsorship-simulation-proxy.php
├── includes/
│   ├── class-dcx-ssp-settings.php       # enabled, trusted_proxies, timeout ONLY
│   ├── class-dcx-ssp-secrets.php        # define / getenv — token
│   ├── class-dcx-ssp-constants.php      # endpoint + literais §4.5
│   ├── class-dcx-ssp-client-ip.php      # algoritmo §6
│   ├── class-dcx-ssp-envelope-a.php
│   ├── class-dcx-ssp-validator.php
│   ├── class-dcx-ssp-envelope-b.php
│   ├── class-dcx-ssp-crm-transport.php
│   ├── class-dcx-ssp-envelope-c.php
│   ├── class-dcx-ssp-logger.php
│   └── class-dcx-ssp-rest-controller.php
└── tests/
    ├── bootstrap.php
    ├── fixtures/
    ├── ... (ver §9)
    └── SchemaParityMutationTest.php
```

---

## 3. Secrets e endpoint (segurança)

### 3.1 Token — ADOTADO

```php
define('DCX_CRM_LEAD_ENDPOINT_SECRET', '...'); // wp-config.php
// ou getenv('DCX_CRM_LEAD_ENDPOINT_SECRET')
```

Ordem fail closed:

```text
1. define não vazio
2. senão getenv não vazio
3. senão → 503 genérico; NÃO chama CRM; log sem valor
```

**REJEITADO:** `wp_options`, admin que grave secret, `wp_localize_script`, query string.

### 3.2 `crm_endpoint` — ADOTADO (Blocker 1)

```php
// class-dcx-ssp-constants.php — literal imutável V1
public const CRM_ENDPOINT = 'https://comercial.dancacarajas.com.br/api/leads/site';
```

```text
crm_endpoint
→ literal de código / constante imutável
→ NÃO wp_options
→ NÃO painel admin
→ NÃO filtro público / apply_filters redirecionável
```

Razão: option alterável permitiria apontar para outro HTTPS e exfiltrar `X-DCF-Lead-Token`.

### 3.3 Settings não secretos (option OK)

Option `dcx_ssp_settings` **somente**:

| Chave | Valor |
|---|---|
| `enabled` | `'1'` / `'0'` |
| `trusted_proxies` | lista CIDR/IPs da cadeia confiável |
| `timeout_seconds` | default `12` (10–15) |

**Proibido** na option: `token`, `secret`, `crm_token`, `crm_endpoint`, `endpoint`.

---

## 4. Constantes determinísticas (border §4.5 + endpoint)

```php
CRM_ENDPOINT               = 'https://comercial.dancacarajas.com.br/api/leads/site'
submission_type            = 'SPONSORSHIP_SIMULATION'
submission_version         = '1.0.0'
snapshot_version           = '1.0.0'
briefing_schema_version    = '1.0.0'
catalog_version            = '2026-V2.1'
pronac_number              = '265397'
edition_year               = 2026   // int
origin_page                = 'patrocinio/seja-patrocinador'
source_url                 = 'https://dancacarajas.com.br/patrocinio/seja-patrocinador/'
form_id                    = 'dcx-sponsorship-simulation-v1'
form_name                  = 'Simular Patrocinio'
```

---

## 5. Pipeline (ordem fixa)

```text
0. Secret resolvido; ausente → 503 fail closed
1. Body bruto ≤ 64 KiB → senão 413 (ANTES de json_decode)
2. JSON decode object → senão 400
3. Validar submission_uuid canônico (UUID v4 lowercase)
   ausente/inválido → 422
4. Honeypot website|website_url preenchido?
   SIM → 201 Envelope C canônico RECEIVED
        { success, status:RECEIVED, submission_uuid, idempotent:false }
        CRM NÃO chamado
        (demais validações podem ser puladas)
5. Allowlist / hard-deny → senão 422
6. Strict: contact_consent === true
   confirmed === true
   confirmed_at RFC3339 → senão 422
   (WP NUNCA gera confirmed / confirmed_at)
7. Demais regras Validator (recommendation, display, limites…)
8. Client IP via algoritmo §6
9. Montar Envelope B determinístico (§7)
10. POST CRM (TLS verify, redirection=0, timeout, headers)
11. Aceitar resposta CRM (§8) → Envelope C
12. Log sem PII/token
```

### Honeypot (Blocker 5 — fechado)

```text
parse JSON
↓
validar apenas submission_uuid canônico
↓
honeypot
```

- UUID inválido/ausente → **422** (mesmo com honeypot)
- UUID válido + honeypot → **201** C canônico com o **mesmo** UUID; CRM não chamado
- **Não** inventar UUID; **não** omitir UUID; **não** criar 4º shape de Envelope C

---

## 6. Client IP — algoritmo trusted proxy (Blocker 3)

```text
1. validar REMOTE_ADDR como IP
2. se REMOTE_ADDR NÃO ∈ trusted_proxies:
     ignorar XFF completamente
     client_ip = REMOTE_ADDR

3. se REMOTE_ADDR ∈ trusted_proxies:
     parsear X-Forwarded-For em lista de IPs válidos (ignorar tokens inválidos)
     caminhar da DIREITA para a ESQUERDA
     ignorando hops que ∈ trusted_proxies

4. primeiro IP NÃO-trusted encontrado = client_ip

5. nenhuma cadeia válida (só trusted / lista vazia / lixo)
     → fail closed: 422 ou 400 genérico (política fixa na implementação;
        não encaminhar lixo; não chamar CRM com IP inventado)
```

**PROIBIDO:** `explode(',')[0]` cego; confiar em XFF se peer não trusted.

Outbound CRM: **um único** header:

```http
X-Forwarded-For: <client_ip>
```

User-Agent original sanitizado ≤512 — recomendado.

---

## 7. Envelope B (Blocker 2 — fechado)

**Não** exigir A≡B byte-a-byte.

```text
EnvelopeB preserva sem regenerar/reinterpretar semanticamente:
- submission_uuid
- confirmed (boolean true já validado)
- confirmed_at (RFC3339 já validado; nunca regenerar)
- recommendation e ordem de alternatives
- valores semânticos do snapshot (briefing/interests/display allowlisted)

Campos sujeitos à canonicalização do border contract
(trim / lower / upper / clip / sanitize texto)
são normalizados EXATAMENTE UMA VEZ e de forma determinística:
- name, company_name, role_title, … → trim (+ limites)
- email → lower + trim
- state → uppercase 2
- message → trim; texto puro; ≤5000
- utm_* → clip ≤120
```

Forçados pelo proxy (sempre): type/version/project/catalog/snapshot/briefing_schema/origin/source/form_*.

Retry: **B determinístico** a partir do mesmo A canônico — não “A idêntico a B”.

---

## 8. Resposta CRM → Envelope C (Blocker 4 — fechado)

### 8.1 Aceitação de HTTP 201

CRM **201** só é aceito quando:

```text
body = JSON object
success === true          (boolean)
idempotent === boolean    (não string "false"/"true")
```

Então:

| Condição | Envelope C |
|---|---|
| `idempotent === false` | HTTP **201** `RECEIVED` |
| `idempotent === true` | HTTP **201** `ALREADY_RECEIVED` |

Qualquer 201 malformed / incompatível (body vazio, `success:false`, `idempotent` string, JSON inválido) → **502** genérico; **não** expor body CRM.

### 8.2 Demais status

| CRM | WP → Browser |
|---|---|
| 409 | **409** genérico; UUID inalterado; sem novo UUID |
| 403 | **502/503** genérico (nunca “token inválido”) |
| 422 | 422; detalhe interno não vaza; `errors` só humanos allowlisted |
| 429 | 429 genérico |
| 5xx / timeout / rede | 502/503 |
| outro 2xx inesperado | **502** |

Envelope C sucesso (sempre):

```json
{
  "success": true,
  "status": "RECEIVED",
  "submission_uuid": "…",
  "idempotent": false
}
```

Strip: `lead_id`, `simulation_id`, token, body bruto.

---

## 9. Contrato de testes

CRM **mockado**. Cobertura obrigatória (não só happy path).

### 9.1 Body / UUID / honeypot

| ID | Caso | Esperado |
|---|---|---|
| T01 | body > 64 KiB | **413**; sem CRM |
| T02 | JSON inválido | 400 |
| T03 | UUID válido + honeypot | **201** C com **mesmo** UUID; **sem CRM** |
| T03b | honeypot + UUID ausente/inválido | **422** |

### 9.2 Strict booleans / confirmed_at

| ID | Caso | Esperado |
|---|---|---|
| T10 | `contact_consent: true` | passa |
| T11 | `contact_consent: 1` / `"sim"` / `"on"` / `"true"` | **422** |
| T12 | `confirmed: true` | passa |
| T13 | `confirmed: "true"` / `1` / ausente | **422** |
| T14 | `confirmed_at` RFC3339 | passa |
| T15 | `confirmed_at` ausente | **422** |
| T16 | `confirmed_at: "tomorrow"` | **422** |
| T17 | WP **não** gera `confirmed_at` | assert |

### 9.3 Allowlist / hard-deny

| ID | Caso | Esperado |
|---|---|---|
| T20 | unknown top-level key | **422** |
| T21 | Engine internals | **422** |
| T22 | label comercial display | **422** |
| T23 | fixture válida → monta B | ok |

### 9.4 Envelope B determinístico

| ID | Caso | Esperado |
|---|---|---|
| T30 | PRONAC errado no A | B força `265397`/`2026` |
| T31 | `form_name` diferente | B força `Simular Patrocinio` |
| T32–T33 | type/versões forçados | literais §4 |
| T34 | uuid/confirmed/confirmed_at preservados | sem regenerar |
| T35 | email com espaços/maiúsculas | B = lower/trim determinístico |
| T36 | UTM >120 | clip determinístico |

### 9.5 IP / trusted proxy

| ID | Caso | Esperado |
|---|---|---|
| T40 | peer não trusted + XFF forjado | **não confia**; usa REMOTE_ADDR |
| T41 | peer trusted + XFF simples | outbound = IP cliente |
| T42 | XFF lixo | fail closed; sem encaminhar lixo |
| T43 | exatamente um XFF outbound | assert |
| T44 | multi-hop trusted chain (direita→esquerda) | client = primeiro não-trusted |
| T45 | attacker-prepended XFF | não escolhe o IP do atacante à esquerda |
| T46 | cadeia IPv6 | parse/seleção correta |
| T47 | CIDR/hop inválido | fail closed / ignore hop inválido conforme §6 |

### 9.6 Transporte / secret / endpoint

| ID | Caso | Esperado |
|---|---|---|
| T50 | tentativa de override endpoint via option/filtro | **ignorada**; usa constante |
| T51 | `sslverify === true` | assert |
| T52 | `redirection === 0` | redirect **não** seguido |
| T53 | timeout finito | assert |
| T54 | token no outbound | assert |
| T55 | token ausente em resposta/log | assert |
| T56 | secret ausente no server | **503** fail closed; sem CRM |
| T57 | `CRM_ENDPOINT` é o literal HTTPS fixo | assert constante |

### 9.7 Envelope C / erros CRM

| ID | Caso | Esperado |
|---|---|---|
| T60 | 201 + success true + idempotent false | **201 RECEIVED**; sem IDs |
| T61 | 201 + success true + idempotent true | **201 ALREADY_RECEIVED** |
| T62 | 409 | **409** genérico |
| T63 | 403 | **502/503**; sem “token” |
| T64 | 422 estrutural | detalhe interno não vaza |
| T65 | timeout / 5xx | **502/503** |
| T66 | 201 body inválido/vazio | **502** |
| T67 | 201 `success:false` | **502** |
| T68 | 201 `idempotent` string | **502** |
| T69 | outro 2xx inesperado | **502** |

### 9.8 Logs / golden

| ID | Caso | Esperado |
|---|---|---|
| T70 | log pós-submit | uuid+status; sem PII/token/JSON B |
| T80 | golden fixture → B → mock 201 → C RECEIVED | ok |

### 9.9 Schema parity / mutation matrix (hardening)

Fixture base válida + **mutações unitárias negativas** (uma regra por caso). Não exige 50 testes manuais nomeados; exige suite que falhe se o Validator for “parcial”.

Cobrir pelo menos:

```text
- submission_uuid não-v4 / uppercase não canônico
- email inválido
- state ≠ CHAR(2) / não upper após canonicalização
- limites de string (name, company, message, role_title, …)
- cardinalidades briefing (objectives/audiences/proof_needs)
- cardinalidades interests + alternatives ≤5
- investment state machine (UNDEFINED|OPEN|DEFINED_AMOUNT|DEFINED_RANGE)
- catalog_ref_id regex
- display_snapshot: só keys permitidas; labels comerciais → 422
- recommendation.state ≠ READY → 422
- availability ≠ NOT_CHECKED → 422
```

Implementação sugerida: `SchemaParityMutationTest` lê fixture + tabela de mutações `{ path, value, expect: 422 }`.

### 9.10 Mapa rápido

```text
unknown key → 422                         T20
confirmed="true" → 422                    T13
contact_consent=1 → 422                   T11
confirmed_at ausente → 422                T15
body >64KiB → 413                         T01
honeypot + UUID → 201 sem CRM             T03
honeypot sem UUID → 422                   T03b
XFF falsificado → não confiado            T40 / T45
multi-hop chain                           T44
CRM 201 novo → RECEIVED                   T60
CRM 201 replay → ALREADY_RECEIVED         T61
CRM 201 malformed → 502                   T66–T68
CRM 409 → 409                             T62
CRM 403 → 502/503                         T63
CRM 422 → sem vazamento                   T64
timeout → 502/503                         T65
redirect → não seguido                    T52
secret ausente → fail closed              T56
endpoint só constante                     T50 / T57
log sem PII/token                         T55 / T70
```

---

## 10. Critério de pronto 22.1

```text
[ ] Plugin sem tema
[ ] REST anonymous; secret define/env
[ ] CRM_ENDPOINT literal; sem option de endpoint
[ ] Algoritmo IP §6
[ ] Envelope B §7 (canonicalização determinística)
[ ] Aceitação 201 §8.1
[ ] Honeypot §5 com C canônico
[ ] Suite §9 verde (incl. T44–T47, T66–T69, mutation matrix)
[ ] Zero diff em CRM / 36fe5a2 / border FROZEN
```

---

## 11. Prompt cirúrgico (rascunho — só após FROZEN)

> Implementar somente `integrations/wordpress/dcx-sponsorship-simulation-proxy/` em `etapa22/wp-proxy-v1` @ `d58e69b`, conforme border v1.2 FROZEN e esta arquitetura **v1.1**. `CRM_ENDPOINT` e literais em constante de código; secret só `DCX_CRM_LEAD_ENDPOINT_SECRET` (define/env). Algoritmo XFF §6; B §7; aceite 201 §8.1; honeypot após UUID. Testes §9 com CRM mockado. Proibido: JS/UI, CRM PHP, `wp_options` para token/endpoint, gerar `confirmed_at`, follow redirects, expor token/IDs, depender de tema/nonce como proteção CRM.

---

## 12. Fora deste documento

- JS Guide / UI / seja-patrocinador
- Alterações no CRM / `36fe5a2` / border contract
- Merge `main` (só após 22.1 verde + auditoria)
