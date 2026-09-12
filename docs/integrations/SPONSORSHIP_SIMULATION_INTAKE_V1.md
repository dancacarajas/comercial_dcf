# SPONSORSHIP_SIMULATION Intake v1 (Etapa 21.2)

Contrato site → CRM (`POST /api/leads/site`) para leads originados da Simulação de Patrocínio (Guide).

## Versões suportadas

| Campo | Valor |
|---|---|
| `submission_version` | `1.0.0` |
| `snapshot_version` | `1.0.0` |
| `catalog_version` | `2026-V2.1` |
| `briefing_schema_version` | `1.0.0` |
| `engine_version` | informativo |
| `policy_version` | informativo |

## Autenticação

- Header: `X-DCF-Lead-Token`
- Honeypot: `website` / `website_url`
- Rate limit: configurável (`LEAD_RATE_LIMIT_*`)
- Replay idempotente (UUID já persistido) **não** é bloqueado por rate limit

## `submission_type`

| Valor | Comportamento |
|---|---|
| ausente / `GENERIC_LEAD` | fluxo legado Etapa 9 |
| `SPONSORSHIP_SIMULATION` | `App\Services\SponsorshipLeadIntake` |

## Envelope mínimo

Ver fixtures em `fixtures/integrations/`:

- `sponsorship_simulation_valid.v1.json`
- `sponsorship_simulation_range.v1.json`
- `sponsorship_simulation_open.v1.json`
- `sponsorship_simulation_invalid_internal_fields.v1.json`

## Identidade do lead

| Campo | Regra |
|---|---|
| `company_name` | **obrigatório** |
| `email` | **obrigatório** (formato válido) |
| `message` | **opcional** — se presente, persiste em `leads.message` e no `integration_payload` sanitizado |

## Regras de aceite (21.2)

### Obrigatórios

- `contact_consent = true`
- `confirmed` = boolean JSON **`true` apenas** (não `"true"`, `"false"`, `1`, `0`, `false`)
- `briefing_schema_version` = **`1.0.0`** (vazio / `9.0.0` / outros → **422**)
- `confirmed_at` em **RFC3339** (`Z` ou offset). Valores relativos (`tomorrow`) → **422**
- `recommendation.state = READY`
- `primary.availability = NOT_CHECKED` (e alternativas)
- `fit_level` ∈ `VERY_HIGH|HIGH|MODERATE|LOW|INCOMPATIBLE|NOT_CALCULATED`
- investment: `UNDEFINED|OPEN|DEFINED_AMOUNT|DEFINED_RANGE`
- `tier_id` deve existir em `quotas.catalog_ref_id` do projeto (PRONAC `265397`), versão `2026-V2.1`, não arquivada
- campos internos proibidos: `internal_score`, `reason_codes`, `diagnostics`, `passport`, `session_id`, etc.

### Limites de cardinalidade (boundary)

| Campo | PASS | FAIL |
|---|---:|---:|
| `briefing.objectives` | ≤8 | 9+ |
| `briefing.audiences` | ≤8 | 9+ |
| `briefing.proof_needs` | ≤10 (`maxItems`) | 11+ |
| `interests.tier_interest` | ≤5 | 6+ |
| `interests.axis_interest` | ≤4 | 5+ |
| `interests.activation_interest` | ≤8 | 9+ |
| `interests.property_interest` | ≤8 | 9+ |
| `interests.asset_interest` | ≤15 | 16+ |
| `recommendation.alternatives` | ≤5 | 6+ |

`proof_needs`: `maxItems` **10**; o intake define **9** valores canônicos (`PROOF_NEEDS`); com `uniqueItems`, o teto prático de uniques é **9** (não exige ≥10 enums).

### Interests opcionais

- `interests: {}` → **201**
- somente `axis_interest` → **201**
- somente `activation_interest` → **201**
- chaves ausentes são tratadas como listas vazias após sanitize

### `catalog_ref_id` (pattern)

- Aceito: `FORMACAO` (e padrão `[A-Z][A-Z0-9_]*`, máx. 80)
- Rejeitado (**422**): espaços (`foo bar`), `#BAD`, comprimento >80

### `display_snapshot` — labels comerciais rejeitados

Campos abaixo → **422** (não forjar UI comercial no envelope):

- `availability_label`
- `fit_label`
- `primary_tier_label`
- `investment_label`

Labels estruturais permitidos: `objective_labels`, `depth_label`, `experience_labels`, `proof_labels`, `axis_label`, `activation_label`.

A UI do CRM usa status estruturais (`availability_status`, etc.), **nunca** labels comerciais do display.

## Respostas

| HTTP | Caso |
|---|---|
| 201 | criado ou replay idempotente (`idempotent: true`) |
| 403 | token inválido |
| 409 | mesmo `submission_uuid` com snapshot diferente |
| 422 | contrato inválido |
| 429 | rate limit (não aplica a replay conhecido) |

## Idempotência / hash (set semantics)

- Chave: `submission_uuid` (UUID)
- Hash SHA-256 sobre payload comercial canônico
- Arrays de `objectives`, `proof_needs` e listas de `interests` são tratados como **conjuntos** (ordem irrelevante)
- Mesmo UUID + mesma permutação de arrays → **201** replay
- Mesmo UUID + conteúdo diferente → **409**

## Persistência

- `leads` — identidade + `submission_type` + `incentive_project_id` + `integration_payload` (recibo técnico)
- `sponsorship_simulations` — domínio comercial imutável (`ON DELETE RESTRICT` em `lead_id`)

## Fresh install (catálogo V2.1)

`database/install_schema.sql` **não** deve conter seeds legados ativos:

- nomes: `Cota Formação`, `Cota Incentivador`, `Círculo Dança Carajás`, Apresenta 200000, Movimento 50000
- valores: `470448`, `42768`

**Deve** conter:

- PRONAC `265397`
- `authorized_capture_amount` `515592.48`
- cinco `catalog_ref_id`: `INCENTIVA`, `MOVIMENTO`, `EXPERIENCE`, `CARAJAS`, `APRESENTA`
- `quotas.amount DECIMAL(14,2)` e `available_quantity` nullable

Projeto canônico no fresh install:

- `approved_total_amount` **NULL**
- `capture_commission_budget` **NULL**
- exatamente 5 cotas V2.1 ativas; 0 nomes legados ativos

Harness: `php tools/validate_etapa21_fresh_install.php` (temp DB `dcf_e21_fresh_test` em `127.0.0.1:3307`).

## Migrations

- Scripts `run_migration_etapa21a_*` / `run_migration_etapa21b_*` **não** auto-definem `127.0.0.1:3307` salvo `DCX_LOCAL_DOCKER_DB_OVERRIDE=1`
- SQL referência `2026_etapa21b_sponsorship_simulation.sql` deve espelhar `NOT NULL` do `CREATE` PHP
- Reconcile 21B: se `sponsorship_simulations` tem registros e falta coluna invariante NOT NULL → **FAIL** (não inventar fit/tier/version/hash/confirmation). Tabela vazia pode ADD estrutural; versões só DEFAULT `'1.0.0'`; sem DEFAULT `'PARTIAL'` / `'INCENTIVA'` semântico

## Quota filter (RANGE)

Cota **Incentiva** (`amount` NULL, `min_amount`/`max_amount` range) deve aparecer ao filtrar `amount_max=30000` via `Quota::paginate`.

## Não faz

- Não executa Recommendation Engine
- Não reserva `quota_id`
- Não cria Proposal / Sponsor
- Não persiste Passport cru como domínio operacional

## Harness

```bash
# Contrato HTTP + integridade 21.1/21.2 + RC migration 21B / intake
TEST_BASE=http://127.0.0.1:8089 php tools/validate_etapa21_sponsorship_simulation.php

# Fresh install (string-scan + temp DB)
php tools/validate_etapa21_fresh_install.php
```
