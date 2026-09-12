# ETAPA 21 — Catálogo Comercial V2.1 — Relatório (parcial / BLOCK)

## Status

**audit status = BLOCKED_FOR_PHYSICAL_DELETE**

Banco local Docker (`danca_captacao`) acessível.  
Exclusão física do Projeto 2026 + cotas legadas **não pode** ser executada sem decisão explícita: há vínculos comerciais reais.

---

## A. Estado encontrado antes

| Item | Valor |
|---|---|
| DB | `danca_captacao` (Docker `dcc_db`, porta host `3307`) |
| Projeto | id=`1` · `Dança Carajás Festival 2026` · year=`2026` |
| PRONAC | `NULL` |
| status | `em_captacao` |
| approved_total_amount | `470448.00` (legado) |
| authorized_capture_amount | `470448.00` (legado) |
| capture_commission_budget | `42768.00` (legado) |
| commission_factor | `0.0909090909` |
| archived_at | `NULL` |

## B. Cotas legadas (6)

| id | name | amount | qty | project |
|---|---|---|---|---|
| 1 | Cota Apresenta | 200000.00 | 1 | 1 |
| 2 | Cota Carajás | 100000.00 | 1 | 1 |
| 3 | Cota Movimento | 50000.00 | 2 | 1 |
| 4 | Cota Formação | 25000.00 | 2 | 1 |
| 5 | Cota Incentivador | 10448.00 | 1 | 1 |
| 6 | Círculo Dança Carajás | NULL | 99 | 1 |

## C. Rubrica antiga

- budget item id=`1`, item_number=`41`, R$ `42768.00`, commission flag=1

## D. Matriz de dependências (projeto id=1 / cotas legadas)

| ENTITY | PROJECT_ID refs | QUOTA_ID legacy refs | IMPACT |
|---|---:|---:|---|
| incentive_project_budget_items | 1 | — | seed/rubrica |
| opportunities | **27** | **23** | **BLOCK DELETE** |
| proposals | **24** | **19** | **BLOCK DELETE** |
| sponsors | **25** | **20** | **BLOCK DELETE** |
| financial_entries | **24** | **4** | **BLOCK DELETE** |
| collector_assignments | **10** | — | **BLOCK DELETE** |
| collector_deals | **8** | — | **BLOCK DELETE** |
| counterparts | **3** | 0 | **BLOCK DELETE** |
| contracts | **7** | **5** | **BLOCK DELETE** |
| documents | **36** | **5** | **BLOCK DELETE** |
| sponsor_dossiers | **4** | 0 | **BLOCK DELETE** |
| commission_pools | **1** | — | **BLOCK DELETE** |
| collector_commissions | 0 | 0 | ok |
| collector_deal_shares | 0 | — | ok |

Não foi usado `FOREIGN_KEY_CHECKS=0`.

## E. Decisão de exclusão

**DELETE físico do projeto #1 e das cotas 1–6 = NEGADO neste momento.**

Motivo: registros comerciais reais (oportunidades, propostas, patrocinadores, financeiro, contratos, documentos, captadores) preservam história e FKs.

### Caminhos possíveis (aguardando GO)

1. **IN-PLACE (recomendado)**  
   - Manter `incentive_projects.id = 1`  
   - Atualizar campos canônicos do projeto (PRONAC, teto autorizado, zerar rubrica/fator inventados)  
   - **Arquivar** cotas legadas (não apagar)  
   - Criar as 5 cotas V2.1 novas com `catalog_ref_id`  
   - História antiga continua apontando para cotas arquivadas via `quota_id`

2. **PURGE + remapeamento**  
   - Nullificar `quota_id` em todos os dependentes  
   - Apagar cotas legadas  
   - Reescrever projeto ou criar novo  
   - **Perde** vínculo histórico de cota

3. **ABORT**  
   - Só entregar schema/código V2.1 sem tocar dados

## F. Achados de repositório (hardcodes ativos)

- `install_schema.sql` seed: 470448 / 42768 / cotas legadas  
- `Opportunity::getQuotaInterests()` lista antiga hardcoded  
- `Quota::FLEXIBLE_NAME = 'Círculo Dança Carajás'`  
- `scripts/run_migration_etapa19_projects.php` recria projeto/rubrica legados  
- Placeholders de formulário `470448,00` / `42768,00`  
- Validators de comissão usam 470448/42768 como fixture de teste

## G. `approved_total_amount`

Nenhuma fonte canônica no workspace distingue o valor oficial de `approved_total_amount` vs teto autorizado.  
**Não fabricar.** Para o projeto canônico: `approved_total_amount = NULL` até haver fonte; `authorized_capture_amount = 515592.48`.

## H. Produção

Nenhum write em produção. Local Docker apenas.

## I. Próximo comando (após GO)

```bash
# Depois da implementação e do modo escolhido:
php scripts/run_migration_etapa21_catalogo_comercial_v21.php --mode=in-place
php tools/validate_etapa21_catalogo_v21.php
```

---

**Aguardando decisão: 1 IN-PLACE · 2 PURGE · 3 ABORT**
