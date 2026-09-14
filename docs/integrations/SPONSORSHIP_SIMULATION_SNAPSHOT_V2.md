# SPONSORSHIP_SIMULATION Snapshot V2 (Etapa 22.3-A)

**Status:** ADITIVO · CRM local · PASS  
**Pré-requisito:** Etapa 21 + 22.1 FROZEN · `main` @ `a9a6f23`  
**Catálogo:** `2026-V2.1` (não renomear)  
**Scenario runtime:** `scenario_version = "2.2.0"`  
**Snapshot:** `snapshot_version = "2.0.0"`  
**Briefing:** `briefing_schema_version = "1.0.0"`

V1 (`snapshot_version=1.0.0`) permanece intacto.  
Dispatch no endpoint existente `POST /api/leads/site`:

| `snapshot_version` | Intake |
|---|---|
| `1.0.0` | `SponsorshipLeadIntake` (congelado) |
| `2.0.0` | `SponsorshipLeadIntakeV2` (novo) |

## Envelope (conceitual)

```json
{
  "submission_type": "SPONSORSHIP_SIMULATION",
  "submission_version": "1.0.0",
  "submission_uuid": "uuid-v4-lowercase",
  "name": "...",
  "company_name": "...",
  "email": "...",
  "contact_consent": true,
  "project": { "pronac_number": "265397", "edition_year": 2026 },
  "sponsorship_simulation": {
    "snapshot_version": "2.0.0",
    "scenario_version": "2.2.0",
    "catalog_version": "2026-V2.1",
    "briefing_schema_version": "1.0.0",
    "engine_version": "2.2.0",
    "policy_version": "2.2.0",
    "confirmed": true,
    "confirmed_at": "RFC3339",
    "briefing": {},
    "interests": {},
    "scenario_result": {},
    "display_snapshot": {}
  }
}
```

## Financeiro

Todos os valores V2 em **centavos inteiros** (`*_cents`). Sem float no contrato V2. `currency = "BRL"`.

| Tier | Regra |
|---|---|
| INCENTIVA | SINGLE · `1000000 ≤ amount < 2500000` |
| MOVIMENTO | exatamente `2500000` · máx 4 |
| EXPERIENCE | exatamente `5000000` · máx 2 |
| CARAJAS | exatamente `10000000` por unit · máx 4 |
| APRESENTA | SINGLE · exatamente `51559248` |

Teto autorizado: `51559248`.

`investment` V2:

```json
{ "status": "DEFINED_AMOUNT|DEFINED_RANGE|OPEN|UNDEFINED", "min_cents": 0, "max_cents": 0, "currency": "BRL" }
```

OPEN/UNDEFINED: `min_cents` e `max_cents` devem ser `null`.

## result_type (enum fechado)

`SINGLE` · `COMPOSITION` · `NO_EXACT_COMPOSITION` · `NO_EXACT_COMPOSITION_IN_RANGE` · `STRATEGIC_ONLY` · `NO_CANONICAL_CONFIGURATION` · `ABOVE_AUTHORIZED_CAP`

## scenario_result

Bloco estruturado persistido integralmente em `sponsorship_simulations.scenario_result_snapshot` (LONGTEXT nullable).  
Coluna auxiliar: `result_type` VARCHAR(64) nullable.

CRM valida formato/invariantes; **não** recalcula Recommendation Engine; **não** completa `axis_id` ausente; **não** reordena `units` / `near_options` / `strategic_options`.

`availability` na intake = somente `NOT_CHECKED`.  
`quota_id` / IDs internos: hard-deny na intake pública.

### SINGLE / COMPOSITION

- COMPOSITION exige `>= 2` units; soma = `total_amount_cents`
- Carajás com `axis_id` explícito não pode repetir o mesmo eixo
- `axis_id` ausente/null permitido (CRM não preenche)
- Incentiva não coexiste com M/E/C/A; Apresenta só SINGLE isolado

### NO_EXACT*

Interest válido. Sem `primary_tier_id`, sem `units`, sem `quota_id`.  
`near_options`: `[{ tier_id, total_amount_cents }]` na ordem recebida.

### STRATEGIC_ONLY

OPEN/UNDEFINED. Sem `total_amount_cents` inventado. `strategic_options` opcional sem amounts.

### NO_CANONICAL_CONFIGURATION / ABOVE_AUTHORIZED_CAP

Interest. Sem units READY. Cap = `51559248`.

## Persistência

- V1: `recommendation_snapshot` (inalterado)
- V2: `scenario_result_snapshot` + `result_type`; `recommendation_snapshot` stub `{ state: V2_SCENARIO }`
- Colunas legado (`investment_min/max` BRL, `primary_tier_ref`) preenchidas para listagem; fonte de verdade comercial V2 = `scenario_result_snapshot`
- Tipos sem primary: `primary_tier_ref = NONE`, `fit_level = NOT_CALCULATED`

## Idempotência

`submission_uuid` + SHA-256 do envelope sanitizado integral (inclui units/amounts/axis/near/strategic/confirmed).  
Mesmo UUID + mesmo hash → 201 replay. Mudança material → 409.

## Resposta CRM interna (endpoint existente)

201 + `{ success, message, lead_id, simulation_id, idempotent }` — borda WordPress pública (22.1 FROZEN) continua sem IDs.
