# ETAPA 22 — Contrato da borda WordPress → CRM

**Status:** contrato canônico **v1.2 — FROZEN**  
**Pré-requisito:** Etapa 21 PRODUCTION FROZEN · commit `36fe5a2`  
**CRM endpoint:** `POST https://comercial.dancacarajas.com.br/api/leads/site`  
**Página Guide:** `/patrocinio/seja-patrocinador/`

Este documento é a **primeira entrega** da Etapa 22.  
Define a borda antes de qualquer código de proxy ou UI.

O contrato CRM (`SponsorshipLeadIntake`) está **congelado**.  
WordPress adapta-se a ele; não o redesenha.  
**`36fe5a2` permanece intocado.**

Referência CRM: `docs/integrations/SPONSORSHIP_SIMULATION_INTAKE_V1.md`  
Fixture de referência: `fixtures/integrations/sponsorship_simulation_valid.v1.json` (conferida em `36fe5a2`)

**Gate documental atual:**

```text
ETAPA 22 BORDER ARCHITECTURE
PASS

FIXTURE CRM V1
PASS

AUDITORIA HUMANA
PASS

DOCUMENT FREEZE
PASS / FROZEN

PROXY WP PHP
AUTORIZADO PARA IMPLEMENTAÇÃO
```

---

## 0. Invariantes de governança (não negociáveis)

1. O navegador **nunca** recebe `LEAD_ENDPOINT_SECRET` / `X-DCF-Lead-Token`.
2. WordPress **não** é autoridade da Recommendation Engine / Policy / Passport.
3. O snapshot enviado ao CRM deve corresponder ao contrato que a Etapa 21 já aceita.
4. Retry / replay preserva idempotência por `submission_uuid` + **hash do envelope sanitizado integral**.
5. Recomendação **não** reserva nem contrata cota (`quota_id` permanece NULL no CRM).
6. Snapshot persistido **não adquire autoridade comercial** só por ter passado no proxy/CRM.
7. Resposta pública ao browser **não** expõe IDs internos (`lead_id`, `simulation_id`) nem body bruto/erros de auth do CRM.
8. WP **transporta/valida** confirmação (`confirmed` / `confirmed_at`); **não as cria**.
9. Rate limit CRM por IP do **visitante** (não do servidor WP) — ver §8.
10. **Não tocar** no contrato congelado da Etapa 21 salvo blocker real descoberto pela integração.

---

## 1. Cadeia de autoridade (semântica)

```text
ENGINE
  gera a recomendação da Journey

↓ snapshot

BROWSER
  transporta estado não confiável

↓

WORDPRESS
  não recalcula
  não melhora
  não troca tier
  não inventa fit
  não altera recommendation
  não gera confirmed / confirmed_at
  aplica allowlist / hard-deny / auth / IP

↓

CRM
  valida contrato
  valida tier recomendado contra o catálogo ativo
  valida formato das demais referências conforme
    SponsorshipLeadIntake congelado
  persiste snapshot
  mantém availability = NOT_CHECKED
  NÃO executa Recommendation Engine
  (apenas valida, armazena e apresenta)

↓

COMERCIAL (humano / CRM operacional)
  continua sendo autoridade sobre
  disponibilidade, negociação, reserva e contratação
```

**Catálogo (verdade do código congelado, não exagerar):**

| Referência | O que o CRM faz na intake |
|---|---|
| `tier_id` (primary + alternatives) | Cross-check de existência contra cota ativa do projeto + `catalog_version` |
| `axis_id`, `activation_id`, `property_id` | Validação **estrutural** de `catalog_ref_id` (formato); **sem** cross-check de existência no catálogo nesta intake |

Não alterar a Etapa 21 para “completar” esse cross-check. Documentar a verdade.

**Consequência:** INTEREST ≠ RECOMMENDED ≠ SELECTED ≠ RESERVED ≠ CONTRACTED.

---

## 2. Fluxo canônico

```text
GUIDE 5/5
        ↓
Recommendation Engine
        ↓
resultado + interesse explícito
        ↓
formulário + confirmed/confirmed_at gerados UMA VEZ no runtime Guide
        ↓
envelope A (NÃO confiável; sem token)
        ↓
proxy WP (allowlist / hard-deny / auth / IP / teto de body)
        ↓
POST /api/leads/site + X-DCF-Lead-Token + X-Forwarded-For:<client-ip>
        ↓
SponsorshipLeadIntake → Lead + SponsorshipSimulation
        ↓
resposta CRM interna → WP
        ↓
envelope C filtrado → browser
```

---

## 3. Três envelopes (não confundir)

| Envelope | Onde vive | Confiável? |
|---|---|---|
| **A. Browser → WP** | body do REST WP | **Não** |
| **B. WP → CRM** | JSON POST CRM | Operacionalmente allowlisted + autenticado — **sem** autoridade comercial |
| **C. WP → Browser** | JSON resposta pública | Sem token, sem IDs internos, sem body bruto do CRM |

---

## 4. Shape exato do envelope B (WP → CRM)

`Content-Type: application/json`  
`X-DCF-Lead-Token: <LEAD_ENDPOINT_SECRET>`  
`X-Forwarded-For: <validated-client-ip>` — ver §8

### 4.0 Teto global de payload (hardening)

| Envelope | Limite | HTTP |
|---|---|---|
| **A** (Browser → WP) | **64 KiB** (corpo bruto) | acima → **413 Payload Too Large** |
| **B** | derivado de A após sanitize; não expandir artificialmente | — |

Limites campo a campo continuam válidos; o teto global é adicional.

### 4.1 Top-level — allowlist estrita

Espelho da allowlist CRM (`additionalProperties=false` no top-level).  
Qualquer chave fora desta lista → **422 no WP** (não encaminha).

| Campo | Origem | WP | Obrigatório | Limite / regra |
|---|---|---|---|---|
| `submission_type` | proxy | **força** | sim | `SPONSORSHIP_SIMULATION` |
| `submission_version` | proxy | **força** | sim | `1.0.0` |
| `submission_uuid` | browser | valida UUID; **não regenera** | sim | UUID v4 canônico (lowercase) |
| `name` | browser | trim | sim | 2..180 |
| `company_name` | browser | trim | sim | 2..180 |
| `role_title` | browser | trim | não | ≤160 |
| `email` | browser | lower/trim | sim | e-mail válido |
| `whatsapp` | browser | trim | não | ≤40 |
| `city` | browser | trim | não | ≤120 |
| `state` | browser | upper 2 | não | `CHAR(2)` |
| `segment` | browser | trim | não | ≤80 |
| `message` | browser | trim | não | ≤5000; texto puro; sem HTML |
| `contact_consent` | browser | **exige boolean JSON `true`** | sim | ver §4.4 |
| `origin_page` | proxy | **força literal** | sim | ver §4.5 |
| `source_url` | proxy | **força literal** | sim | ver §4.5 |
| `form_id` | proxy | **força literal** | sim | ver §4.5 |
| `form_name` | proxy | **força literal** | sim | ver §4.5 |
| `utm_source` / `utm_medium` / `utm_campaign` / `utm_content` / `utm_term` | browser (congelados no submit) | clip ≤120 | não | participam do hash |
| `project` | proxy | **força** | sim | ver §4.2 |
| `sponsorship_simulation` | browser | allowlist; **não recalcula** | sim | ver §4.3 |
| `website` / `website_url` | honeypot | se preenchido: **não chama CRM** | — | anti-spam |

### 4.2 `project` — autoridade do proxy

```json
{
  "pronac_number": "265397",
  "edition_year": 2026
}
```

- `pronac_number` = string `"265397"`
- `edition_year` = número JSON `2026`

### 4.3 `sponsorship_simulation`

| Campo | Browser | Proxy | Regra CRM |
|---|---|---|---|
| `snapshot_version` | sim | **força** `1.0.0` | exato |
| `engine_version` | sim | valida não-vazio ≤20 | informativo |
| `policy_version` | sim | valida não-vazio ≤20 | informativo |
| `catalog_version` | sim | **força** `2026-V2.1` | exato |
| `briefing_schema_version` | sim | **força** `1.0.0` | exato |
| `presenter_version` | sim | opcional ≤20 | opcional |
| `confirmed` | **obrigatório** boolean `true` | **valida; NUNCA gera** | `=== true` |
| `confirmed_at` | **obrigatório** RFC3339 | **valida; NUNCA gera** | RFC3339 |
| `briefing` | sim | allowlist | intake |
| `interests` | sim | allowlist | `{}` ok |
| `recommendation` | sim | allowlist; não melhora | `READY` + `NOT_CHECKED` |
| `display_snapshot` | sim | editorial only | sem labels comerciais |

#### `confirmed` (crítico)

| Valor | Resultado |
|---|---|
| `true` (boolean JSON) | aceito |
| `false`, `"true"`, `"false"`, `1`, `"1"`, `"on"`, `null`, ausente | **422** |

**WordPress não fabrica `confirmed: true`.**

#### `confirmed_at` (Blocker 1 — resolvido)

```text
confirmed_at
→ obrigatório no envelope A
→ criado uma única vez pelo runtime Guide
   no instante da confirmação
→ congelado junto com submission_uuid
→ WP apenas valida RFC3339 (Z ou offset)
→ WP NUNCA gera
→ retry reutiliza exatamente o valor congelado
```

**Proibido no proxy:** gerar `now()`, completar campo ausente, ou armazenar mapa `UUID → confirmed_at` no WP para “lembrar” timestamps.  
Ausente ou inválido → **422** (não chama CRM).

Simetria com `confirmed`: WP transporta/valida a confirmação; **não a cria**.

#### Briefing

Obrigatórios: `area_decision`, `objectives`, `depth_intent`, `investment`, `proof_needs`  
Opcional: `audiences`

| Campo | max |
|---|---:|
| objectives | 8 |
| audiences | 8 |
| proof_needs | 10 (`maxItems`; teto prático unique = 9 enums) |

#### Interests

`interests: {}` válido. Pattern `catalog_ref_id`: `^[A-Z][A-Z0-9_]*$`, 2..80.

| Lista | max |
|---|---:|
| tier_interest | 5 |
| axis_interest | 4 |
| activation_interest | 8 |
| property_interest | 8 |
| asset_interest | 15 |

#### Recommendation

- Proxy não roda Engine, não troca tier, não inventa fit.
- `state` = somente `READY`.
- `availability` = somente `NOT_CHECKED` (nunca converter para disponibilidade comercial).
- `tier_id` será cross-checked no CRM contra catálogo ativo; demais refs = formato estrutural.
- `alternatives` ≤ 5; ranking não reordenado.
- Proibido: `reason_codes`, `internal_score`, `diagnostics`, Passport, `session_id`.

#### `display_snapshot` permitido

`objective_labels`, `depth_label`, `experience_labels`, `proof_labels`, `axis_label`, `activation_label`

**Proibido:** `investment_label`, `primary_tier_label`, `fit_label`, `availability_label`

### 4.4 `contact_consent` (borda 22 estrita)

| Valor | Resultado WP |
|---|---|
| boolean JSON `true` | aceita |
| `"true"`, `"sim"`, `"yes"`, `"on"`, `1`, `"1"`, ausente, `false` | **422 — não chama CRM** |

Não exige mudar Etapa 21 (legado `mapIncoming` permanece para outros fluxos).

### 4.5 Constantes hasheadas do proxy (Blocker 3 — literais congelados)

Estes valores **entram no `snapshot_hash`**. Alteração editorial entre deploys com o mesmo UUID → **409**.  
Congelar **literalmente** (espelho da fixture `36fe5a2`):

| Campo | Valor exato |
|---|---|
| `origin_page` | `patrocinio/seja-patrocinador` |
| `source_url` | `https://dancacarajas.com.br/patrocinio/seja-patrocinador/` |
| `form_id` | `dcx-sponsorship-simulation-v1` |
| `form_name` | `Simular Patrocinio` |

Não dependem da copy da UI.  
Não “corrigir” acento em `form_name` sem bump de contrato / novo `submission_version`.

---

## 5. Campos que o browser pode enviar (envelope A)

- identidade: `name`, `company_name`, `role_title`, `email`, `whatsapp`, `city`, `state`, `segment`, `message`
- `contact_consent` — somente boolean `true` no JSON
- `submission_uuid` — gerado uma vez; reutilizado em retry
- `sponsorship_simulation` completo, incluindo **`confirmed: true`** e **`confirmed_at` RFC3339** (obrigatórios)
- UTMs já congelados no objeto de submit
- honeypot vazio: `website_url`

**Body A > 64 KiB → 413.**

**Browser nunca envia/recebe:** token CRM, `lead_id`, `simulation_id`, Engine internals.

---

## 6. Campos que o proxy gera ou sobrescreve

| Campo | Ação |
|---|---|
| `submission_type` | força `SPONSORSHIP_SIMULATION` |
| `submission_version` | força `1.0.0` |
| `project` | força `{ "pronac_number": "265397", "edition_year": 2026 }` |
| `catalog_version` | força `2026-V2.1` |
| `snapshot_version` | força `1.0.0` |
| `briefing_schema_version` | força `1.0.0` |
| `origin_page` | força literal §4.5 |
| `source_url` | força literal §4.5 |
| `form_id` / `form_name` | força literais §4.5 |
| `contact_consent` | aceita somente boolean `true` |
| `confirmed` | **valida somente**; nunca gera |
| `confirmed_at` | **valida somente**; nunca gera |
| `X-DCF-Lead-Token` | só server-side |
| `X-Forwarded-For` | IP do visitante validado (§8) |
| `User-Agent` (outbound) | recomendado: UA original sanitizado/truncado |

**Proxy NÃO:** regenera UUID; regenera `confirmed_at`; recalcula recommendation; altera availability; inventa UTMs; resolve 409 com novo UUID.

---

## 7. `submission_uuid`, hash e idempotência

### 7.1 Semântica

```text
submission_uuid representa UMA submissão confirmada imutável.

Após 201:
  mesmo UUID + mesmo envelope canônico
  → replay idempotente HTTP 201

  mesmo UUID + qualquer alteração material
  → 409

Nova submissão / alteração após persistida:
  → novo submission_uuid
  (somente por nova ação explícita do usuário)
```

**409 nunca gera automaticamente outro UUID.**  
409 encerra aquele retry. Novo UUID só nasce de nova ação explícita do usuário.

### 7.2 Hash CRM (Etapa 21 — congelado)

`snapshot_hash` = SHA-256 do **envelope sanitizado integral**:

1. allowlist / sanitize
2. conjuntos: `objectives`, `audiences`, `proof_needs`, listas de `interests` (ordem irrelevante)
3. `ksort` recursivo em objetos
4. `recommendation.alternatives` **não** reordenado
5. `json_encode` → SHA-256

Participam do hash: identidade, `message`, consentimento, UTMs, constantes §4.5, `project`, e o bloco `sponsorship_simulation` inteiro (incl. `confirmed` / `confirmed_at`).

### 7.3 Armadilha de JS

Com o mesmo UUID, retry **não pode**: regenerar `confirmed_at`; re-ler UTMs da URL; alterar qualquer campo; “corrigir” recommendation.

---

## 8. Identidade de IP e rate limit (Blocker 2 — resolvido)

### 8.1 Problema

CRM lê IP nesta ordem: `X-Forwarded-For` → `X-Real-IP` → `REMOTE_ADDR`.  
Default: **5 tentativas / 10 minutos / IP**.

Se o proxy não encaminhar o IP do visitante, todas as submissões colapsam no IP do servidor WordPress → 6ª empresa legítima pode tomar **429**.

### 8.2 Regra obrigatória do proxy

1. Determinar o IP do visitante **server-side** por política de **trusted proxy** (cadeia confiável definida na infra WP).
2. Validar que o resultado é um IP legítimo (IPv4/IPv6 parseável; rejeitar lixo).
3. Enviar ao CRM **um único** header:

```http
X-Forwarded-For: <validated-client-ip>
```

(O CRM prioriza `X-Forwarded-For`. `X-Real-IP` sozinho é insuficiente como estratégia canônica desta borda.)

4. **PROIBIDO** copiar cegamente `HTTP_X_FORWARDED_FOR` recebido pelo WordPress do browser — pode ser falsificado sem trusted proxy.

### 8.3 User-Agent (recomendado, não blocker)

Encaminhar `User-Agent` original do visitante, sanitizado e truncado (ex. ≤512 chars).  
Sem isso, o CRM registra o UA do cliente HTTP do WP, não o navegador do lead.

### 8.4 Rate limit em camadas

| Camada | Escopo |
|---|---|
| WP | rate limit próprio (opcional/adicional) por visitante |
| CRM | 5 / 10 min por IP **do visitante** (via §8.2) |
| CRM | replay UUID já persistido **não** consome cota |

---

## 9. HTTP — status e envelopes de resposta

### 9.1 Browser ↔ WordPress (Blocker 4 — resolvido)

Como a Etapa 21 retorna **201** tanto na criação quanto no replay, a borda WP **não inventa** semântica HTTP nova:

| Caso | HTTP WP → Browser | Envelope C `status` |
|---|---|---|
| primeira persistência | **201** | `RECEIVED` |
| replay idempotente | **201** | `ALREADY_RECEIVED` |
| validação WP | 400/422 | `REJECTED` |
| body > 64 KiB | **413** | `REJECTED` |
| conflito hash | **409** | `REJECTED` |
| rate limit | **429** | `REJECTED` |
| CRM/transporte | **502/503** | `REJECTED` |

**Não usar 200** para sucesso nesta borda V1.

### 9.2 WordPress ↔ CRM

| HTTP | Caso |
|---|---|
| 201 | criado ou replay (`idempotent`) |
| 403 | token inválido (bug de config WP) |
| 409 | UUID com envelope canônico diferente |
| 422 | contrato inválido |
| 429 | rate limit (exceto replay conhecido) |
| 500/503 | erro servidor CRM |

### 9.3 Envelope C — resposta pública

**Sucesso (primeira):**

```json
{
  "success": true,
  "status": "RECEIVED",
  "submission_uuid": "…",
  "idempotent": false
}
```

**Sucesso (replay):**

```json
{
  "success": true,
  "status": "ALREADY_RECEIVED",
  "submission_uuid": "…",
  "idempotent": true
}
```

**Erro:**

```json
{
  "success": false,
  "status": "REJECTED",
  "message": "Não foi possível enviar. Verifique os dados e tente novamente.",
  "errors": {
    "email": "…"
  }
}
```

### 9.4 Allowlist objetiva de `errors` públicos

Podem aparecer chaves em `errors` **somente** para campos humanos:

- `name`, `company_name`, `role_title`, `email`, `whatsapp`, `message`, `contact_consent`
- opcionalmente: `city`, `state`, `segment`

**Qualquer** falha de: `project`, versões, Engine/snapshot, `recommendation`, `briefing` estrutural, `confirmed`/`confirmed_at` (mensagem genérica ok sem detalhe interno), infraestrutura, token, catálogo → **mensagem genérica** sem expor estrutura interna.

### 9.5 Erros upstream

| CRM | WP → Browser |
|---|---|
| 201 | envelope C `RECEIVED` / `ALREADY_RECEIVED` |
| 409 | 409 + genérico (não gerar novo UUID) |
| 422 | 422 + `errors` só se §9.4; senão genérico |
| 403 | 502/503 genérico; log ops server-side |
| 429 | 429 genérico |
| 5xx / timeout | 502/503 genérico |

---

## 10. Transporte outbound WP → CRM (hardening)

| Regra | Valor |
|---|---|
| Endpoint | fixo: `https://comercial.dancacarajas.com.br/api/leads/site` |
| Esquema | **HTTPS obrigatório** |
| TLS | verificação ativa (não desligar verify peer/host) |
| Redirects | **proibidos** (`follow_redirects = false`) — o header secreto não pode acompanhar redirect |
| Timeout | definido e finito (ex. 10–15s) — valor exato na implementação |

---

## 11. Logs do proxy (hardening)

**Permitido:** correlation id, `submission_uuid`, HTTP status CRM, duração, código interno WP.

**Proibido:**

- `LEAD_ENDPOINT_SECRET` / token
- envelope B bruto (contém PII: nome, e-mail, WhatsApp, mensagem, etc.)
- envelope A bruto em produção
- body de erro de autenticação CRM
- Passport / reason_codes / diagnostics

---

## 12. Retry / anti-spam

| Camada | Regra |
|---|---|
| Browser | mesmo UUID + mesmo envelope congelado |
| WP | honeypot; teto 64 KiB; IP validado; rate limit próprio opcional |
| CRM | rate limit por IP visitante; UNIQUE(`submission_uuid`) autoridade final |

---

## 13. O que nunca sai do servidor WordPress

- `LEAD_ENDPOINT_SECRET`
- `lead_id` / `simulation_id` no envelope C
- logs com token ou PII do envelope B
- Passport / reason_codes / diagnostics
- body bruto de auth CRM

JS público: **somente** `proxyUrl` (REST WP).

---

## 14. Semântica comercial

| Conceito | Significado |
|---|---|
| INTEREST | usuário avançou após Guide |
| RECOMMENDED | snapshot Engine validado/persistido; sem autoridade comercial |
| SELECTED / RESERVED / CONTRACTED | não criados por este fluxo |
| `availability` | sempre `NOT_CHECKED` pré-CRM |
| `quota_id` | NULL até decisão humana |

---

## 15. Hard-deny (não exaustivo)

`token`, `lead_token`, `api_key`, `secret`, `reason_codes`, `internal_score`, `diagnostics`, `passport`, `session_id`, `quota_id`, `opportunity_id`, `sponsor_id`, `lead_id`, `simulation_id`, labels comerciais de display, `approved_total_amount`, `commission_*` inventados, qualquer chave fora das allowlists.

---

## 16. Ordem de implementação

1. Este contrato → **FROZEN** ← *concluído*
2. Proxy WP PHP + testes (allowlist, IP, idempotência, 64 KiB, TLS/no-redirect) ← *próxima frente*
3. JS Guide → proxy; congela UUID + `confirmed_at` + UTMs no submit
4. Wiring `/patrocinio/seja-patrocinador/`
5. QA E2E
6. Tráfego real

**Não** começar UI / `/seja-patrocinador/` antes do proxy cumprir este contrato.  
**Não** reabrir Etapa 21 (`36fe5a2`).


---

## 17. Critério de pronto (gate documental)

- [x] Três envelopes + autoridade
- [x] Token só server-side
- [x] Versões / projeto / catálogo forçados
- [x] Literais §4.5 congelados (fixture)
- [x] `confirmed` / `confirmed_at` só Guide; WP nunca gera
- [x] `contact_consent` boolean estrito
- [x] Recommendation `READY` / `NOT_CHECKED`
- [x] Hash integral + UUID imutável + 409 sem auto-UUID
- [x] IP visitante via `X-Forwarded-For` validado (trusted proxy)
- [x] Sucesso/replay = HTTP **201** + `RECEIVED` / `ALREADY_RECEIVED`
- [x] Envelope C sem IDs internos
- [x] Catálogo: só `tier_id` com cross-check; demais refs estruturais
- [x] Fixture `sponsorship_simulation_valid.v1.json` conferida contra `36fe5a2`
- [x] Hardening: 64 KiB, TLS/no-redirect, logs sem PII, errors públicos objetivos
- [x] **Revisão curta final v1.2 → carimbo FROZEN**

---

## 18. Fora de escopo

- Alterar `SponsorshipLeadIntake` / schema CRM / `36fe5a2`
- Engine no WordPress
- Reserva de cota
- E-mail transacional
- Expor IDs ao browser
- Mapa WP `UUID → confirmed_at`
- Redesign visual além do wiring mínimo
