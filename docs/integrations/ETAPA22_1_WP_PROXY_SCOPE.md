# ETAPA 22.1 — WordPress server-side proxy

**Status:** ESCOPO **v1.1 — FROZEN**  
**Base:** `d58e69b` — BORDER CONTRACT v1.2 FROZEN  
**Branch de implementação:** `etapa22/wp-proxy-v1`  
**Branch documental imutável:** `etapa22/border-contract-v1.2` (não alterar)  
**CRM baseline:** `36fe5a2` — INTOCADO  

Contrato normativo: `docs/integrations/ETAPA22_WORDPRESS_CRM_BORDER_V1.md`  
Arquitetura: `docs/integrations/ETAPA22_1_WP_PROXY_ARCHITECTURE_V1.md` **v1.1 FROZEN**

**Gate atual:**

```text
PACKAGING ............ PASS
ROTA .................. PASS
SECRET MODEL .......... PASS
ENDPOINT MODEL ........ PASS
PIPELINE ............... PASS
TEST CONTRACT .......... PASS

DOCUMENT FREEZE ........ PASS / FROZEN
CÓDIGO ................ AUTORIZADO
```

---

## Escopo autorizado

```text
✓ endpoint REST WordPress (público/anonymous)
✓ parser do Envelope A
✓ limite bruto 64 KiB
✓ honeypot (após UUID canônico; Envelope C canônico)
✓ allowlist + hard-deny
✓ validação strict boolean (=== true)
✓ confirmed_at RFC3339 (WP nunca gera)
✓ Envelope B determinístico (canonicalização border; sem “melhorar” snapshot)
✓ constantes canônicas (§4.5) + crm_endpoint LITERAL de código
✓ trusted-proxy / cadeia XFF explícita
✓ X-Forwarded-For (um IP validado)
✓ token CRM: define/env only (NÃO wp_options)
✓ HTTPS + TLS verify + redirects 0 + timeout finito
✓ CRM 201 só aceito com body JSON válido (success/idempotent boolean)
✓ mapeamento CRM → Envelope C
✓ filtragem de erros
✓ logs sem PII/token
✓ testes T01–T80 + T44–T47 + T56 + T66–T69 + schema parity matrix
```

## Fora de escopo

```text
✗ JS Guide / Passport / Engine / UI / seja-patrocinador
✗ alterações no CRM / 36fe5a2 / border contract d58e69b
✗ crm_endpoint em wp_options / admin / filtro público
✗ token em wp_options
✗ depender de tema / nonce WP como proteção do token CRM
```

## Forma de entrega

Plugin próprio `integrations/wordpress/dcx-sponsorship-simulation-proxy/` — independente de tema.

## Ordem interna

1. Arquitetura v1.1 FROZEN ← *concluído*  
2. Implementação PHP do plugin + suíte de testes ← *em curso*  
3. Auditoria final código + relatório  
4. Depois: JS Guide + página
