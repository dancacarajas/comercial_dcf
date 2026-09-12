# ETAPA 21.1 — Contract & Integrity Hardening

## Veredito local (pré-auditoria externa)

| Gate | Status |
|---|---|
| Contrato de intake (enums canônicos + allowlist) | PASS (local) |
| Idempotência canônica + race UUID | PASS (local) |
| display_snapshot não sobrescreve estado comercial | PASS (local) |
| FK Simulation lead_id ON DELETE RESTRICT | PASS (local) |
| Migration 21A preflight determinístico | PASS (código) |
| Migration 21B reconcile + FAIL em FK | PASS (local) |
| Fresh install schema Etapa 21 | PASS (install_schema.sql) |
| Hard-delete Lead/Cota fora do escopo 21 | PASS (removido; Etapa 9 = 19/19 HEAD) |
| Produção | BLOCKED |
| WordPress | BLOCKED |

## Decisão G — Rate limit × replay

1. Após honeypot, se `submission_type=SPONSORSHIP_SIMULATION` e UUID já existir → **bypass do gate 429**.
2. Replay idempotente (`idempotent=true`) **não registra** tentativa no rate limit.
3. Submissões novas e GENERIC_LEAD continuam limitadas (anti-spam intacto).

## Baseline Etapa 9

Hard-delete **não** faz parte da Etapa 21. Baseline oficial: **19/19 PASS** (HEAD).

## Resultados de teste

- `validate_etapa21_catalog.php`: 20 PASS / 0 FAIL
- `validate_etapa21_sponsorship_simulation.php`: 74 PASS / 0 FAIL
- `validate_etapa9.php`: 19/19 PASS

## Escopo excluído do pacote

- Etapa 22
- scripts/validators de e-mail 21A–21E
- alterações `.env.example` de assinatura eletrônica
- WordPress / produção

