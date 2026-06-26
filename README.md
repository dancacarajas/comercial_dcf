# Dança Carajás Captação

CRM cultural de captação de patrocínio para o **Dança Carajás Festival 2026**.

> **Etapa 1 — Base técnica da stack.** Esta entrega contém apenas a fundação:
> MVC leve, conexão PDO, segurança inicial e tabelas administrativas.
> Os módulos do CRM (empresas, contatos, oportunidades, cotas, propostas etc.)
> serão construídos nas próximas etapas.

---

## Stack

- PHP 8.2+
- MySQL 5.7+ / MariaDB 10.3+
- PDO com prepared statements (sem `mysqli` procedural)
- Arquitetura MVC leve, sem dependências externas
- Compatível com hospedagem compartilhada Hostinger (sem Docker, Node, filas ou workers)
- Identidade visual oficial (`dcx-theme.css`): Quicksand + Nunito Sans, paleta DCX e ícones **Lucide** (via CDN)

---

## Estrutura de pastas

```
.
├── .htaccess                  # Redireciona tudo para /public (caso o docroot não seja /public)
├── .env.example               # Modelo de variáveis de ambiente
├── .gitignore
├── README.md
│
├── public/                    # ÚNICA pasta exposta na web
│   ├── index.php              # Front controller
│   ├── .htaccess              # Rewrite + headers de segurança
│   └── assets/
│       ├── css/dcx-theme.css  # Tema visual oficial (identidade DCX)
│       ├── js/app.js          # JS leve + inicialização dos ícones
│       └── vendor/lucide/lucide.min.js   # Ícones Lucide LOCAIS (sem CDN)
│
├── app/
│   ├── .htaccess              # Bloqueia acesso web direto
│   ├── Core/
│   │   ├── App.php            # Kernel (boot, autoload, sessão, timeout, erros)
│   │   ├── Router.php         # Roteador HTTP leve
│   │   ├── Controller.php     # Controller base (+ auth helpers)
│   │   ├── Model.php          # Model base (PDO)
│   │   ├── Database.php       # Conexão PDO (singleton)
│   │   └── View.php           # Renderizador de views + layout
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── AuthController.php       # login / logout / forgot
│   │   ├── DashboardController.php  # painel protegido
│   │   ├── UserController.php       # CRUD admin de usuários
│   │   ├── RoleController.php       # perfis + permissões
│   │   └── PermissionController.php # listagem de permissões
│   ├── Models/
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   └── ActivityLog.php
│   ├── Views/
│   │   ├── layouts/{admin,auth}.php
│   │   ├── home/index.php
│   │   ├── auth/{login,forgot}.php
│   │   ├── dashboard/index.php
│   │   ├── users/{index,create,edit,show}.php
│   │   ├── roles/{index,edit,show}.php
│   │   ├── permissions/index.php
│   │   └── errors/{403,404,500}.php
│   ├── Services/
│   ├── Middlewares/
│   │   └── AuthMiddleware.php
│   └── Helpers/
│       ├── env.php            # Carregador de .env + função env()
│       ├── security.php       # e(), clean(), input(), validate(), flash(), destroy_session()...
│       └── csrf.php           # csrf_token(), csrf_field(), csrf_verify()
│
├── config/
│   ├── .htaccess
│   ├── app.php                # Nome, ambiente, URL, sessão, timeout, política de login
│   ├── database.php           # Credenciais PDO
│   └── mail.php               # SMTP (uso futuro)
│
├── routes/
│   ├── .htaccess
│   └── web.php                # Definição de rotas
│
├── storage/                   # Privado (fora da web)
│   ├── .htaccess
│   ├── logs/.gitkeep          # app.log é gravado aqui
│   ├── uploads/.htaccess      # Uploads privados + bloqueio de execução PHP
│   ├── exports/.gitkeep
│   └── backups/.gitkeep
│
└── database/
    ├── .htaccess              # Bloqueia download de .sql pela web
    ├── schema.sql             # Tabelas administrativas + seed inicial
    └── migrations/
        ├── 2026_etapa2_users_auth.sql        # Colunas de auth p/ instalações já existentes
        └── 2026_etapa3_roles_permissions.sql # Perfis + permissões + matriz
```

---

## Instalação local (teste rápido)

```bash
# A partir da raiz do projeto
php -S 127.0.0.1:8000 -t public
```

Acesse `http://127.0.0.1:8000`. Deve aparecer **"Dança Carajás Captação — Base instalada"**.

---

## Instalação na Hostinger (hospedagem compartilhada)

### 1. Banco de dados

No **hPanel → Bancos de Dados → MySQL**:

1. Crie um **novo banco** (ex.: `u123456789_captacao`).
2. Crie um **usuário** (ex.: `u123456789_user`) e defina uma **senha forte**.
3. **Associe** o usuário ao banco com **todos os privilégios**.
4. Abra o **phpMyAdmin** do banco e importe o arquivo `database/schema.sql`
   (aba **Importar → Escolher arquivo → Executar**).

### 2. Envio dos arquivos

Há duas formas de organizar o projeto:

**Opção A (recomendada) — Document Root apontando para /public**
1. Envie a pasta do projeto para fora de `public_html`, por exemplo `/home/usuario/dancacarajas`.
2. Em **hPanel → Sites → (seu domínio) → Configurações avançadas**, defina o
   **diretório raiz/Document Root** para `.../dancacarajas/public`.
3. Assim, apenas `/public` fica exposto e o `.htaccess` raiz nem é necessário.

**Opção B — Tudo dentro de public_html**
1. Envie todo o conteúdo do projeto para dentro de `public_html`.
2. O arquivo `.htaccess` da raiz já redireciona todas as requisições para `/public`,
   mantendo `app/`, `config/`, `routes/` e `storage/` protegidos.

> Em ambos os casos, certifique-se de que o **mod_rewrite** está ativo (padrão na Hostinger).

### 3. Configurar credenciais (banco, usuário e senha)

Copie `.env.example` para `.env` na **raiz do projeto** e preencha:

```env
APP_URL=https://captacao.seudominio.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=  # gere com: php -r "echo bin2hex(random_bytes(32));"

DB_HOST=localhost
DB_DATABASE=u123456789_captacao
DB_USERNAME=u123456789_user
DB_PASSWORD=suaSenhaDoBanco
```

> Se o seu plano **não permitir** `.env`, você pode editar diretamente os valores
> em `config/database.php` e `config/app.php` (eles têm valores padrão de fallback).

### 4. Permissões da pasta storage

Garanta que `storage/` e subpastas tenham permissão de escrita (geralmente `755`,
e `775` se necessário). É onde ficam logs (`storage/logs/app.log`), uploads, exports e backups.

### 5. Usuário administrador inicial

O `schema.sql` cria um admin para testes:

- **E-mail:** `admin@dancacarajas.com`
- **Senha:** `Mudar@123`

> ⚠️ **Troque imediatamente** em produção. Gere um novo hash com
> `php -r "echo password_hash('SuaSenhaForte', PASSWORD_DEFAULT);"`
> e atualize o campo `password_hash` na tabela `users`.

---

## Autenticação (Etapa 2)

### Acessar o login
- Abra `https://seudominio/login`.
- Se já houver sessão ativa, você é redirecionado para `/dashboard`.

### Credenciais temporárias do admin
| Campo | Valor |
|-------|-------|
| E-mail | `admin@dancacarajas.com` |
| Senha  | `Mudar@123` |

O admin é criado com `must_change_password = 1`, sinalizando que a senha **deve** ser trocada.

> 🔒 **ALERTA DE SEGURANÇA:** altere a senha temporária **antes de ir para produção**.
> Recomenda-se também **remover ou alterar** o seed do admin após a instalação.

### Como trocar a senha (até existir a tela de troca, na próxima etapa)
1. Gere um novo hash:

```bash
php -r "echo password_hash('SuaSenhaForte', PASSWORD_DEFAULT);"
```

2. No phpMyAdmin, atualize o usuário:

```sql
UPDATE users
SET password_hash = '<HASH_GERADO>', must_change_password = 0
WHERE email = 'admin@dancacarajas.com';
```

### Importar / atualizar o banco
- **Instalação nova:** importe `database/schema.sql` (já contém as colunas de autenticação).
- **Instalação que já existia da Etapa 1:** rode apenas a migração
  `database/migrations/2026_etapa2_users_auth.sql` para adicionar as colunas
  `must_change_password`, `remember_token`, `failed_login_attempts`, `locked_until`.

### Como testar
- **Login:** acesse `/login`, informe e-mail/senha corretos → vai para `/dashboard`.
- **Logout:** no topo do painel, clique em **Sair** → encerra a sessão e volta para `/login`.
- **Proteção de rota:** acesse `/dashboard` **sem** sessão → redireciona para `/login`.
- **Força bruta:** erre a senha 5 vezes → a 6ª tentativa é bloqueada por 15 min (HTTP 429).
- **Erro genérico:** senha incorreta sempre exibe *"E-mail ou senha inválidos."* (nunca revela se o e-mail existe).
- **/health em produção** (`APP_DEBUG=false`): retorna apenas `{"status":"ok"}`.

---

## Usuários, Perfis e Permissões (Etapa 3)

### Rodar a migration da Etapa 3
- **Instalação nova:** importe `database/schema.sql` (já traz os 5 perfis, permissões e a matriz).
- **Instalação já existente:** importe `database/migrations/2026_etapa3_roles_permissions.sql`
  (idempotente — pode rodar mais de uma vez sem duplicar; usa `slug` como referência).

### Acessar
- **Usuários:** `/users` · **Perfis:** `/roles` · **Permissões:** `/permissions`
  (itens do menu aparecem apenas para quem tem a permissão correspondente).

### Criar um novo usuário
1. Acesse `/users` → **Novo usuário** (requer `users.create`, exclusivo do Administrador Geral).
2. Informe nome, e-mail, senha provisória (mín. 8) + confirmação, status e **um ou mais perfis**.
3. O usuário é criado com `must_change_password = 1` (troca obrigatória no 1º acesso).

### Vincular perfis a um usuário
- Em `/users/{id}/edit`, marque os perfis desejados e salve. A senha **não** é editada aqui.
- Para nova senha provisória: tela do usuário → **Redefinir senha** (gera senha segura e exige troca).

### Editar permissões de um perfil
- Em `/roles/{id}/edit`, marque/desmarque permissões e salve.
- O perfil **Administrador Geral** mantém **todas** as permissões por segurança (não pode ser reduzido).

### Matriz de permissões aplicada
| Perfil | Permissões |
|--------|-----------|
| **Administrador Geral** (`administrador-geral`) | **Todas** |
| **Captação / Comercial** (`captacao-comercial`) | `dashboard.view`, `companies.*`, `contacts.*`, `opportunities.*`, `quotas.view`, `tasks.view`, `leads.view`, `proposals.view`, `documents.view` |
| **Produção / Coordenação** (`producao-coordenacao`) | `dashboard.view`, `sponsors.view`, `counterparts.view`, `documents.view`, `reports.view` |
| **Comunicação** (`comunicacao`) | `dashboard.view`, `sponsors.view`, `counterparts.view`, `documents.view`, `reports.view` |
| **Leitura / Consulta** (`leitura-consulta`) | `dashboard.view` + `*.view` de consulta (companies, contacts, opportunities, quotas, tasks, leads, proposals, documents, sponsors, counterparts, reports) |

> As permissões de módulos (`companies.*`, `contacts.*`, etc.) **existem no banco** apenas para
> preparar o controle de acesso. **Nenhum módulo de CRM foi criado nesta etapa.**

> ⚠️ **Alerta sobre o Administrador Geral:** é o único perfil com gestão de usuários/perfis.
> Mantenha pelo menos um usuário ativo nesse perfil e troque a senha temporária do admin inicial.

### Checklist de testes (Etapa 3)
- [ ] `GET /users` sem login → redireciona para `/login`.
- [ ] `GET /users` como admin → **200**.
- [ ] `GET /users` sem `users.view` → **403**.
- [ ] Criar usuário com CSRF inválido → **419**.
- [ ] Criar usuário válido → criado com hash seguro e `must_change_password = 1`.
- [ ] Editar usuário → registra log `user_updated`.
- [ ] Tentar inativar o próprio admin → **bloqueado**.
- [ ] Redefinir senha → hash atualizado e `must_change_password = 1`.
- [ ] `GET /roles` como admin → **200**; editar permissões registra `role_permissions_updated`.
- [ ] Tentar remover todas as permissões do Administrador Geral → **bloqueado** (mantém todas).
- [ ] `GET /permissions` como admin → **200**.
- [ ] Menu exibe Usuários/Perfis/Permissões apenas conforme permissão.

---

## Etapa 4 — Módulo Empresas / Prospects

Primeiro módulo real do CRM: cadastro e gestão de **empresas potenciais patrocinadoras**
do Dança Carajás Festival 2026. **Sem exclusão física** — empresas usam *arquivamento
lógico* (`archived_at`) e podem ser restauradas.

### Como rodar a migration da Etapa 4

Nova instalação: o `database/schema.sql` já inclui a tabela `companies`.

Instalação existente, aplique a migration:

```bash
# Hostinger (hPanel → phpMyAdmin → Importar) ou via CLI:
mysql -u SEU_USUARIO -p SEU_BANCO < database/migrations/2026_etapa4_companies.sql

# Ambiente Docker local:
docker compose exec -T db sh -c 'mariadb -udanca -pdanca danca_captacao' < database/migrations/2026_etapa4_companies.sql
```

As permissões `companies.view` / `companies.create` / `companies.edit` **já existem**
desde a Etapa 3 (não é preciso criar novas). O arquivamento/restauração reutiliza
`companies.edit` (nenhuma permissão nova foi criada).

### Estrutura da tabela `companies`

| Grupo | Campos |
| --- | --- |
| Dados principais | `name` (obrigatório), `trade_name`, `cnpj` (nullable, só dígitos) |
| Localização/segmento | `segment`, `city`, `state` (UF) |
| Contato geral | `website`, `linkedin`, `general_email`, `general_phone` |
| Atuação territorial | `operates_para`, `operates_carajas`, `operates_parauapebas` |
| Estratégico | `tax_regime_guess`, `has_cultural_sponsorship_history`, `has_rouanet_history`, `has_esg_alignment`, `priority` (A–D), `source`, `status`, `owner_user_id`, `notes` |
| Auditoria | `created_by`, `updated_by`, `created_at`, `updated_at`, `archived_at` |

Índices: `name`, `cnpj`, `segment`, `(city,state)`, `priority`, `status`, `owner_user_id`.
Chaves estrangeiras (`owner_user_id`, `created_by`, `updated_by`) → `users(id)` com
`ON DELETE SET NULL`.

Listas controladas (no `App\Models\Company`): **segmentos**, **prioridades** (A/B/C/D),
**status** (`prospect`, `em_qualificacao`, `prioritario`, `monitoramento`, `sem_aderencia`,
`arquivado`), **regimes tributários** e **origens da indicação**.

### Permissões usadas

| Ação | Permissão |
| --- | --- |
| Acessar `/companies`, ver detalhes | `companies.view` |
| Criar empresa | `companies.create` |
| Editar, arquivar e restaurar | `companies.edit` |

Sem a permissão exigida o usuário recebe **403**. Todas as ações `POST` exigem **CSRF**.

### Rotas

```
GET  /companies               lista + filtros + paginação   (companies.view)
GET  /companies/create        formulário de cadastro        (companies.create)
POST /companies               grava nova empresa            (companies.create)
GET  /companies/{id}          visualização                  (companies.view)
GET  /companies/{id}/edit     formulário de edição          (companies.edit)
POST /companies/{id}/update   grava alterações              (companies.edit)
POST /companies/{id}/archive  arquivamento lógico           (companies.edit)
POST /companies/{id}/restore  restauração                   (companies.edit)
```

### Como usar

- **Acessar:** menu **Empresas** (ícone `building-2`, visível só com `companies.view`) ou `/companies`.
- **Cadastrar:** botão **Nova empresa** → preencha (nome obrigatório; CNPJ opcional, validado em 14 dígitos quando informado) → salvar redireciona para a visualização.
- **Listar/filtrar:** busca por nome/fantasia/CNPJ + filtros por segmento, prioridade, status, UF, responsável e atuação (Pará/Carajás/Parauapebas). Paginação de 15 por página. Ordenação: prioridade A primeiro, depois atualização mais recente, depois nome.
- **Editar:** botão **Editar** (empresa arquivada não é editável sem restaurar).
- **Arquivar:** botão **Arquivar** na visualização (não exclui; some da listagem padrão). Marque **Exibir arquivadas** no filtro para vê-las.
- **Restaurar:** na empresa arquivada, escolha o novo status e **Restaurar**.

Logs em `activity_logs`: `company_created`, `company_updated`, `company_archived`, `company_restored`.

### Checklist de testes (Etapa 4)
- [ ] `GET /companies` sem login → redireciona para `/login`.
- [ ] `GET /companies` sem `companies.view` → **403** (e menu Empresas oculto).
- [ ] `GET /companies` com `companies.view` → **200**.
- [ ] `GET /companies/create` sem `companies.create` → **403**.
- [ ] `POST /companies` com CSRF inválido → **419**.
- [ ] `POST /companies` sem nome → erro de validação (**422**).
- [ ] `POST /companies` com CNPJ inválido → erro de validação (**422**).
- [ ] `POST /companies` válido → cria e redireciona para a visualização; CNPJ salvo só com dígitos.
- [ ] `GET /companies/{id}` e `/edit` → **200**.
- [ ] `POST /companies/{id}/update` → atualiza e registra `company_updated`.
- [ ] `POST /companies/{id}/archive` → arquiva sem excluir; some da listagem padrão.
- [ ] Filtro **Exibir arquivadas** mostra a empresa arquivada.
- [ ] `POST /companies/{id}/restore` → restaura (`archived_at` nulo).
- [ ] Filtros (busca/segmento/prioridade/status/UF/atuação) e paginação funcionam.
- [ ] Dashboard exibe o card **Empresas cadastradas** (para quem tem `companies.view`).
- [ ] Identidade visual DCX e ícones Lucide locais preservados.

> Nesta Etapa 4 **não** foram criados: contatos, oportunidades, cotas, tarefas, leads,
> propostas, documentos, patrocinadores, contrapartidas, relatórios, funil comercial
> nem histórico de interações.

---

## Etapa 5 — Módulo Contatos

Pessoas estratégicas (decisores, influenciadores, interlocutores) vinculadas
**obrigatoriamente** a uma empresa do módulo Empresas / Prospects. Sem exclusão
física — apenas arquivamento lógico (`archived_at`).

### Rodando a migration

```bash
# Local (mysql/mariadb client)
mysql -u USUARIO -p NOME_DO_BANCO < database/migrations/2026_etapa5_contacts.sql

# Docker (este projeto)
Get-Content -Raw database/migrations/2026_etapa5_contacts.sql | docker exec -i -e MYSQL_PWD=danca dcc_db mariadb -udanca danca_captacao
```

A migration é idempotente (`CREATE TABLE IF NOT EXISTS`). Para instalações novas,
a tabela `contacts` também já consta em `database/schema.sql`.

### Estrutura da tabela `contacts`

| Coluna | Tipo | Observações |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED PK AI | |
| `company_id` | BIGINT UNSIGNED **NOT NULL** | FK → `companies(id)` `ON DELETE CASCADE` |
| `name` | VARCHAR(180) **NOT NULL** | mínimo 2 caracteres |
| `position_title` | VARCHAR(160) NULL | cargo |
| `department` | VARCHAR(100) NULL | área (lista controlada) |
| `email` | VARCHAR(180) NULL | nullable; validado se informado |
| `whatsapp` | VARCHAR(40) NULL | normalizado (somente dígitos) |
| `phone` | VARCHAR(40) NULL | |
| `linkedin` | VARCHAR(255) NULL | validado para domínio linkedin.com |
| `decision_level` | VARCHAR(40) | default `nao_informado` |
| `influence_level` | VARCHAR(40) | default `media` |
| `preferred_channel` | VARCHAR(40) | default `nao_informado` |
| `last_interaction_at` | DATETIME NULL | |
| `next_contact_at` | DATETIME NULL | usado para "vencidos" |
| `status` | VARCHAR(40) | default `ativo` |
| `notes` | TEXT NULL | |
| `owner_user_id` | BIGINT UNSIGNED NULL | FK → `users(id)` `ON DELETE SET NULL` |
| `created_by` / `updated_by` | BIGINT UNSIGNED NULL | FK → `users(id)` `ON DELETE SET NULL` |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NULL | |
| `archived_at` | DATETIME NULL | arquivamento lógico |

Índices: `company`, `name`, `email`, `department`, `decision_level`,
`influence_level`, `status`, `next_contact_at`, `owner`.

> **Importante:** se a empresa for excluída fisicamente, os contatos são removidos
> em cascata; o arquivamento de empresa **não** remove contatos — eles passam a
> exibir o aviso de "empresa arquivada".

### Permissões usadas

Reaproveita as permissões da Etapa 3 (nenhuma nova foi criada):

- `contacts.view` — acessar `/contacts`, ver detalhes e o bloco na empresa.
- `contacts.create` — criar contatos.
- `contacts.edit` — editar, **arquivar** e **restaurar**.

Sem permissão → **403**. Toda ação POST exige **CSRF**.

### Rotas

| Método | Rota | Permissão |
| --- | --- | --- |
| GET | `/contacts` | `contacts.view` |
| GET | `/contacts/create` (aceita `?company_id={id}`) | `contacts.create` |
| GET | `/companies/{id}/contacts/create` (contextual) | `contacts.create` |
| POST | `/contacts` | `contacts.create` |
| GET | `/contacts/{id}` | `contacts.view` |
| GET | `/contacts/{id}/edit` | `contacts.edit` |
| POST | `/contacts/{id}/update` | `contacts.edit` |
| POST | `/contacts/{id}/archive` | `contacts.edit` |
| POST | `/contacts/{id}/restore` | `contacts.edit` |

### Como usar

- **Acessar:** menu lateral → **Contatos** (visível só com `contacts.view`).
- **Cadastrar:** `/contacts/create`, selecione a empresa, informe ao menos o nome.
- **Cadastrar já vinculado:** dentro de `/companies/{id}` clique em **Novo contato**
  (abre `/companies/{id}/contacts/create` com a empresa pré-selecionada).
- **Editar:** `/contacts/{id}/edit` (bloqueado se arquivado — restaure antes).
- **Arquivar/Restaurar:** botões em `/contacts/{id}` (restaurar permite escolher o status).
- **Ver dentro da empresa:** `/companies/{id}` exibe o bloco **Contatos da empresa**
  (quantidade de ativos, lista resumida e link "Ver todos" filtrado por empresa).

Ações registradas em `activity_logs`: `contact_created`, `contact_updated`,
`contact_archived`, `contact_restored`.

### Checklist de testes (29/29 ✅)

- [x] `php -l` sem erros em todos os arquivos novos/alterados.
- [x] Migration da Etapa 5 executada sem erro (tabela `contacts`, 22 colunas).
- [x] `GET /contacts` sem login → redireciona para `/login`.
- [x] `GET /contacts` sem `contacts.view` → **403**.
- [x] `GET /contacts` com `contacts.view` → **200**; menu **Contatos** só aparece com a permissão.
- [x] `GET /contacts/create` sem `contacts.create` → **403**.
- [x] `POST /contacts` com CSRF inválido → **419**.
- [x] Validações: sem empresa / empresa inexistente / sem nome / e-mail inválido → erro.
- [x] `POST /contacts` válido → cria e redireciona para a visualização.
- [x] WhatsApp salvo normalizado (somente dígitos).
- [x] `GET /contacts/{id}` e `/edit` → **200**.
- [x] `POST /contacts/{id}/update` → atualiza e registra `contact_updated`.
- [x] `POST /contacts/{id}/archive` → arquiva sem excluir; some da listagem padrão.
- [x] Filtro **Exibir arquivados** mostra o contato arquivado.
- [x] `POST /contacts/{id}/restore` → restaura (`archived_at` nulo).
- [x] Filtros (busca, empresa, área, decisão, influência, status, próximo vencido) e paginação funcionam.
- [x] `/companies/{id}` mostra o bloco **Contatos da empresa** (só com `contacts.view`).
- [x] Botão **Novo contato** na empresa só para quem tem `contacts.create`.
- [x] Dashboard exibe o card **Contatos cadastrados**.
- [x] Identidade visual DCX e ícones Lucide locais preservados.

> Nesta Etapa 5 **não** foram criados: oportunidades, cotas, tarefas, leads, propostas,
> documentos, patrocinadores, contrapartidas, relatórios, funil comercial nem histórico
> de interações comerciais avançado.

---

## Etapa 6 — Módulo Oportunidades / CRM de Captação

Núcleo do funil comercial: liga **empresa + contato + status do funil + valor
estimado + probabilidade + próxima ação**. Vínculo obrigatório a uma empresa;
contato opcional (mas, se informado, precisa pertencer à empresa). Sem exclusão
física — apenas arquivamento lógico (`archived_at`).

> Nesta etapa **não** existe tabela de cotas. O interesse de cota é registrado em
> `quota_interest` (texto controlado provisório), para migração futura quando o
> módulo Cotas (Etapa 7) for criado.

### Rodando a migration

```bash
# Local (mysql/mariadb client)
mysql -u USUARIO -p NOME_DO_BANCO < database/migrations/2026_etapa6_opportunities.sql

# Docker (este projeto)
Get-Content -Raw database/migrations/2026_etapa6_opportunities.sql | docker exec -i -e MYSQL_PWD=danca dcc_db mariadb -udanca danca_captacao
```

Idempotente (`CREATE TABLE IF NOT EXISTS`). Para instalações novas, a tabela
`opportunities` também consta em `database/schema.sql`.

### Estrutura da tabela `opportunities`

| Coluna | Tipo | Observações |
| --- | --- | --- |
| `id` | BIGINT UNSIGNED PK AI | |
| `company_id` | BIGINT UNSIGNED **NOT NULL** | FK → `companies(id)` `ON DELETE CASCADE` |
| `contact_id` | BIGINT UNSIGNED NULL | FK → `contacts(id)` `ON DELETE SET NULL` |
| `title` | VARCHAR(180) **NOT NULL** | mínimo 3 caracteres |
| `quota_interest` | VARCHAR(80) NULL | texto controlado provisório |
| `estimated_value` | DECIMAL(12,2) NULL | normalizado (aceita 100.000,00) |
| `probability` | TINYINT UNSIGNED | 0–100; default 5 |
| `status` | VARCHAR(60) | default `prospect_identificado` (17 status) |
| `source` | VARCHAR(120) NULL | origem controlada |
| `owner_user_id` | BIGINT UNSIGNED NULL | FK → `users(id)` `ON DELETE SET NULL` |
| `opened_at` | DATETIME **NOT NULL** | default = data/hora atual |
| `last_interaction_at` | DATETIME NULL | atualizado em mudança de status |
| `next_action_at` | DATETIME NULL | usado para "vencidas" |
| `urgency_level` | VARCHAR(40) | default `normal` |
| `lost_reason` | VARCHAR(180) NULL | obrigatório quando status `perdido` |
| `notes` | TEXT NULL | |
| `created_by`/`updated_by` | BIGINT UNSIGNED NULL | FK → `users(id)` `ON DELETE SET NULL` |
| `created_at` / `updated_at` / `archived_at` | DATETIME | auditoria + arquivamento |

Índices: `company`, `contact`, `status`, `probability`, `owner`, `next_action`,
`opened_at`, `archived_at`.

**Automação leve de probabilidade:** ao escolher o status, a probabilidade
sugerida é preenchida (no front e reforçada no backend). `fechado` força 100%,
`perdido` força 0%. Arquivar **não** altera o status automaticamente.

### Permissões usadas

Reaproveita as permissões da Etapa 3 (nenhuma nova foi criada):

- `opportunities.view` — acessar `/opportunities`, `/opportunities/pipeline`, ver detalhes e os blocos em empresa/contato.
- `opportunities.create` — criar oportunidades.
- `opportunities.edit` — editar, mudar status, **arquivar** e **restaurar**.

Sem permissão → **403**. Toda ação POST exige **CSRF**.

### Rotas

| Método | Rota | Permissão |
| --- | --- | --- |
| GET | `/opportunities` | `opportunities.view` |
| GET | `/opportunities/pipeline` | `opportunities.view` |
| GET | `/opportunities/create` (aceita `?company_id=&contact_id=`) | `opportunities.create` |
| GET | `/companies/{id}/opportunities/create` (contextual) | `opportunities.create` |
| GET | `/contacts/{id}/opportunities/create` (contextual) | `opportunities.create` |
| POST | `/opportunities` | `opportunities.create` |
| GET | `/opportunities/{id}` | `opportunities.view` |
| GET | `/opportunities/{id}/edit` | `opportunities.edit` |
| POST | `/opportunities/{id}/update` | `opportunities.edit` |
| POST | `/opportunities/{id}/status` | `opportunities.edit` |
| POST | `/opportunities/{id}/archive` | `opportunities.edit` |
| POST | `/opportunities/{id}/restore` | `opportunities.edit` |

### Como usar

- **Acessar:** menu lateral → **Oportunidades** (visível só com `opportunities.view`).
- **Pipeline:** `/opportunities/pipeline` — colunas por status, com contagem e soma de valor por coluna (10 cards por coluna; "Ver todas" leva à lista filtrada).
- **Cadastrar:** `/opportunities/create`, selecione a empresa e o título.
- **Já vinculada a empresa:** em `/companies/{id}` clique **Nova oportunidade** (`/companies/{id}/opportunities/create`).
- **Já vinculada a contato:** em `/contacts/{id}` clique **Nova oportunidade** (`/contacts/{id}/opportunities/create`, empresa e contato pré-selecionados).
- **Editar:** `/opportunities/{id}/edit` (bloqueado se arquivada — restaure antes).
- **Mudar status (rápido):** bloco "Ação rápida de status" em `/opportunities/{id}` (POST `/status`).
- **Arquivar/Restaurar:** botões em `/opportunities/{id}`.
- **Ver na empresa/contato:** blocos "Oportunidades da empresa" e "Oportunidades vinculadas".

Ações registradas em `activity_logs`: `opportunity_created`, `opportunity_updated`,
`opportunity_status_changed`, `opportunity_archived`, `opportunity_restored`.

### Checklist de testes (41/41 ✅)

- [x] `php -l` sem erros em todos os arquivos novos/alterados.
- [x] Migration da Etapa 6 executada sem erro (tabela `opportunities`, 21 colunas).
- [x] `GET /opportunities` sem login → redireciona para `/login`.
- [x] `GET /opportunities` sem `opportunities.view` → **403**; com a permissão → **200**.
- [x] Menu **Oportunidades** só aparece com `opportunities.view`.
- [x] `GET /opportunities/create` sem `opportunities.create` → **403**.
- [x] `POST /opportunities` com CSRF inválido → **419**.
- [x] Validações: sem empresa / empresa inexistente / contato fora da empresa / sem título / valor inválido / perdido sem motivo → erro **422**.
- [x] Status `fechado` força probabilidade 100; `perdido` força 0.
- [x] `POST /opportunities` válido → cria, normaliza valor, aplica probabilidade sugerida e redireciona para a visualização.
- [x] `GET /opportunities/{id}` e `/edit` → **200**.
- [x] `POST /opportunities/{id}/update` → atualiza e registra `opportunity_updated` (+ `opportunity_status_changed` quando o status muda).
- [x] `POST /opportunities/{id}/status` → muda status, aplica probabilidade sugerida e registra `opportunity_status_changed`.
- [x] `POST /opportunities/{id}/archive` → arquiva sem excluir; some da listagem padrão; filtro "Exibir arquivadas" mostra; `restore` restaura.
- [x] Filtros (busca, empresa, contato, status, probabilidade, cota, origem, urgência, responsável, vencidas, abertas/fechadas/perdidas) e paginação funcionam.
- [x] `GET /opportunities/pipeline` → **200** com colunas por status, contagem e soma de valor.
- [x] `/companies/{id}` e `/contacts/{id}` mostram os blocos de oportunidades (só com `opportunities.view`); botão "Nova oportunidade" só com `opportunities.create`.
- [x] Dashboard exibe **Oportunidades abertas** + **valor em negociação**.
- [x] Identidade visual DCX e ícones Lucide locais preservados.

> Nesta Etapa 6 **não** foram criados: cotas (módulo próprio), tarefas, leads,
> propostas, documentos, patrocinadores, contrapartidas, relatórios avançados nem
> histórico de interações comerciais avançado. → Entregues na Etapa 7 (cotas).

---

## Etapa 7 — Cotas de Patrocínio

Cria a tabela real de **cotas** e integra-as às oportunidades. O campo textual
`quota_interest` é **mantido** por compatibilidade/histórico, mas o vínculo real
passa a ser feito por `quota_id`.

### Rodar a migration

```bash
# Docker (recomendado para desenvolvimento)
docker cp database/migrations/2026_etapa7_quotas.sql dcc_db:/tmp/e7.sql
docker exec -e MYSQL_PWD=danca dcc_db sh -c "mariadb -udanca danca_captacao < /tmp/e7.sql"

# Hostinger / linha de comando
mysql -u USUARIO -p BANCO < database/migrations/2026_etapa7_quotas.sql
```

A migration é **idempotente** (pode rodar várias vezes): cria a tabela com
`IF NOT EXISTS`, adiciona colunas/índices em `opportunities` com `IF NOT EXISTS`,
adiciona a FK só se ainda não existir e semeia as cotas oficiais usando `name`
como referência (não duplica).

### Estrutura da tabela `quotas`

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | auto incremento |
| `name` | VARCHAR(120) NOT NULL | nome da cota |
| `commercial_name` | VARCHAR(160) NULL | nome comercial |
| `amount` | DECIMAL(12,2) NULL | valor (NULL = flexível) |
| `available_quantity` | INT UNSIGNED | quantidade disponível |
| `reserved_quantity` | INT UNSIGNED | quantidade reservada |
| `closed_quantity` | INT UNSIGNED | quantidade fechada |
| `description` | TEXT NULL | descrição |
| `ideal_profile` | TEXT NULL | perfil indicado |
| `status` | VARCHAR(40) | `disponivel` por padrão |
| `display_order` | INT UNSIGNED | ordem de exibição |
| `notes` | TEXT NULL | observações |
| `created_by` / `updated_by` | BIGINT UNSIGNED NULL | FK → `users(id)` `ON DELETE SET NULL` |
| `created_at` / `updated_at` / `archived_at` | DATETIME | auditoria + arquivamento lógico |

Índices: `idx_quotas_name`, `idx_quotas_status`, `idx_quotas_amount`,
`idx_quotas_display_order`, `idx_quotas_archived_at`.

### Alteração na tabela `opportunities`

- `quota_id` BIGINT UNSIGNED NULL → FK `quotas(id)` `ON DELETE SET NULL` (índice `idx_opportunities_quota`).
- `quota_reserved_until` DATETIME NULL (índice `idx_opportunities_quota_reserved_until`).
- `quota_interest` VARCHAR(80) **preservado** (auxiliar/legado).

### Cotas oficiais semeadas

| Cota | Valor | Disp. | Ordem |
|---|---|---|---|
| Cota Apresenta | R$ 200.000,00 | 1 | 1 |
| Cota Carajás | R$ 100.000,00 | 1 | 2 |
| Cota Movimento | R$ 50.000,00 | 2 | 3 |
| Cota Formação | R$ 25.000,00 | 2 | 4 |
| Cota Incentivador | R$ 10.448,00 | 1 | 5 |
| Círculo Dança Carajás | flexível (NULL) | 99 | 6 |

Status inicial de todas: `disponivel`.

### Permissões

- Usadas: `quotas.view` (já existia desde a Etapa 3).
- **Criadas nesta etapa:** `quotas.create`, `quotas.edit` (cobre editar, arquivar e restaurar).
- Matriz atualizada na migration:
  - **Administrador Geral:** todas (inclui create/edit).
  - **Captação / Comercial:** `quotas.view`, `quotas.create`, `quotas.edit`.
  - **Leitura / Consulta, Produção / Coordenação, Comunicação:** apenas `quotas.view`.

### Rotas

```
GET  /quotas                 (quotas.view)
GET  /quotas/create          (quotas.create)
POST /quotas                 (quotas.create)
GET  /quotas/{id}            (quotas.view)
GET  /quotas/{id}/edit       (quotas.edit)
POST /quotas/{id}/update     (quotas.edit)
POST /quotas/{id}/archive    (quotas.edit)
POST /quotas/{id}/restore    (quotas.edit)
```

### Uso

- **Acessar:** menu **Cotas** (visível para quem tem `quotas.view`).
- **Cadastrar:** `/quotas/create` → nome obrigatório (mín. 2), valor opcional (≥ 0),
  quantidades inteiras ≥ 0; reservada + fechada não pode ultrapassar a disponível
  (exceto **Círculo Dança Carajás**, flexível); status e perfil em listas controladas.
- **Editar:** `/quotas/{id}/edit` (cota arquivada exige restauração antes).
- **Arquivar/Restaurar:** sem exclusão física (`archived_at`); arquivada some da
  listagem padrão e reaparece com o filtro **Exibir arquivadas**.
- **Vincular a uma oportunidade:** no formulário da oportunidade selecione **Cota de
  patrocínio** (`quota_id`). Se o valor estimado estiver vazio e a cota tiver `amount`,
  ele é preenchido automaticamente (nunca sobrescreve valor já informado). Para o
  status **Reserva de cota** é possível informar `quota_reserved_until` (opcional;
  sem data, a oportunidade é salva com aviso). Cotas suspensas/fechadas podem ser
  vinculadas, mas com aviso.

### `quota_id` × `quota_interest`

- `quota_id` é o **vínculo real** com a tabela `quotas` (priorizado nas telas).
- `quota_interest` é **texto auxiliar/legado**, mantido para histórico e para
  oportunidades antigas. As telas exibem o nome da cota real quando houver; caso
  contrário, exibem o `quota_interest`.

### Quantidade manual × resumo calculado

- Os campos `available/reserved/closed_quantity` são **manuais** (editados pelo
  administrador). Nesta etapa o sistema **não** altera essas quantidades
  automaticamente ao fechar/reservar oportunidades.
- A tela da cota mostra, **separadamente**, um **resumo calculado** a partir das
  oportunidades vinculadas (total, abertas, em reserva de cota, fechadas e soma de
  valor estimado) — apenas como apoio à gestão.

### Logs de auditoria

`quota_created`, `quota_updated`, `quota_archived`, `quota_restored` e
`opportunity_quota_linked` (quando uma oportunidade recebe/troca de `quota_id`).

### Checklist de validação (executado — 38/38)

- [x] `php -l` sem erros em todos os arquivos novos/alterados.
- [x] Migration da Etapa 7 executada sem erro; seed das cotas oficiais sem duplicidade (idempotente).
- [x] `GET /quotas` sem login → **302** para `/login`.
- [x] `GET /quotas` logado **sem** `quotas.view` → **403**.
- [x] `GET /quotas` logado **com** `quotas.view` → **200**; menu **Cotas** visível.
- [x] `GET /quotas/create` sem `quotas.create` → **403**.
- [x] `POST /quotas` CSRF inválido → **419**; sem nome / valor inválido / quantidade inválida → **422**.
- [x] `POST /quotas` válido → cria e redireciona para `/quotas/{id}`.
- [x] `GET /quotas/{id}` e `/edit` → **200**; `update` persiste e registra log.
- [x] `archive` arquiva sem excluir; some da lista padrão; **Exibir arquivadas** mostra; `restore` reativa.
- [x] Filtros (busca, status, valor mínimo/máximo) e paginação funcionam.
- [x] Oportunidade antiga (só `quota_interest`) continua abrindo sem erro.
- [x] Formulário de oportunidade lista cotas ativas; `quota_id` inexistente → **422**; válido vincula.
- [x] Troca de `quota_id` registra `opportunity_quota_linked`; valor auto-preenchido pela cota.
- [x] `reserva_de_cota` permite `quota_reserved_until`; show/listagem/pipeline/blocos de empresa e contato exibem a cota real.
- [x] Show da cota exibe oportunidades vinculadas + resumo calculado separado.
- [x] Dashboard mostra card de cotas; identidade visual e Lucide locais preservados.

> **Próxima etapa: Tarefas / Follow-ups (Etapa 8)** — vencimentos, cobranças de
> retorno e próximas ações das oportunidades.
> Nesta Etapa 7 **não** foram criados: tarefas, leads do site, propostas, documentos,
> patrocinadores, contrapartidas, relatórios avançados, geração de contratos nem
> geração de patrocinador fechado. As quantidades de cota permanecem manuais.

---

## Etapa 8 — Tarefas e Follow-ups

Módulo para controlar próximas ações, cobranças de retorno, ligações, WhatsApp,
e-mails, reuniões, follow-ups e pendências internas da captação. Cada tarefa pode
ser vinculada **opcionalmente** a empresa, contato e/ou oportunidade (tarefas
internas são permitidas).

### Como rodar a migration da Etapa 8

```bash
# Via Docker (recomendado — preserva UTF-8)
docker cp database/migrations/2026_etapa8_tasks.sql dcc_db:/tmp/e8.sql
docker exec -e MYSQL_PWD=danca dcc_db sh -c "mariadb -udanca danca_captacao < /tmp/e8.sql"
```

A migration é **idempotente**: `CREATE TABLE IF NOT EXISTS`, `INSERT ... ON DUPLICATE KEY UPDATE`
para permissões e atribuição por perfil. Pode ser executada mais de uma vez sem erro.
Em uma instalação nova, o `database/schema.sql` já inclui a tabela `tasks`.

### Estrutura da tabela `tasks`

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `title` | VARCHAR(180) NOT NULL | mínimo 3 caracteres |
| `description` | TEXT NULL | |
| `type` | VARCHAR(60) DEFAULT `follow_up` | lista controlada |
| `company_id` | BIGINT UNSIGNED NULL | FK `companies(id)` ON DELETE SET NULL |
| `contact_id` | BIGINT UNSIGNED NULL | FK `contacts(id)` ON DELETE SET NULL |
| `opportunity_id` | BIGINT UNSIGNED NULL | FK `opportunities(id)` ON DELETE SET NULL |
| `assigned_user_id` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `due_date` | DATE NULL | |
| `due_time` | TIME NULL | |
| `priority` | VARCHAR(40) DEFAULT `normal` | lista controlada |
| `status` | VARCHAR(40) DEFAULT `pendente` | lista controlada |
| `result` | TEXT NULL | |
| `completed_at` | DATETIME NULL | preenchido ao concluir |
| `completed_by` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `created_by` / `updated_by` | BIGINT UNSIGNED NULL | FK `users(id)` ON DELETE SET NULL |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NULL | |
| `archived_at` | DATETIME NULL | **arquivamento lógico (sem exclusão física)** |

Índices: `title`, `type`, `company_id`, `contact_id`, `opportunity_id`,
`assigned_user_id`, `(due_date, due_time)`, `priority`, `status`, `archived_at`.

**Listas controladas (no Model `Task`):**

- **Tipos:** `ligacao`, `whatsapp`, `email`, `reuniao`, `envio_proposta`, `follow_up`,
  `atualizacao_documentos`, `atualizacao_dados_oficiais`, `cobranca_retorno`,
  `registro_reuniao`, `envio_agradecimento`, `outro`.
- **Prioridades:** `baixa`, `normal`, `alta`, `critica`.
- **Status:** `pendente`, `em_andamento`, `concluida`, `atrasada`, `cancelada`,
  `reagendada`, `arquivada`.
- **Status calculado auxiliar `vencida`:** quando `due_date`/`due_time` estão no passado
  e o status não é `concluida`, `cancelada` ou `arquivada` (não persiste em banco; é
  computado por `Task::isOverdue()` e gera destaque visual).

### Permissões usadas e adicionadas

- **Já existia (Etapa 3):** `tasks.view`.
- **Criadas nesta etapa:** `tasks.create`, `tasks.edit`, `tasks.complete`.
  (`tasks.edit` cobre editar, **arquivar e restaurar**; `tasks.complete` cobre
  **concluir e reabrir**.)

Matriz de perfis aplicada:

| Perfil | Permissões de tarefas |
|---|---|
| Administrador Geral | todas |
| Captação / Comercial | view, create, edit, complete |
| Produção / Coordenação | view, create, edit, complete |
| Comunicação | view, create, edit, complete |
| Leitura / Consulta | apenas view |

Regras: sem `tasks.view` → não acessa `/tasks`; sem `tasks.create` → não cria; sem
`tasks.edit` → não edita/arquiva/restaura; sem `tasks.complete` → não conclui/reabre.
Todas as ações POST exigem CSRF; acesso sem permissão retorna **403**.

### Rotas

```
GET  /tasks                         (tasks.view)   listagem com filtros e paginação
GET  /tasks/create                  (tasks.create) aceita ?company_id ?contact_id ?opportunity_id
POST /tasks                         (tasks.create)
GET  /tasks/{id}                    (tasks.view)
GET  /tasks/{id}/edit               (tasks.edit)
POST /tasks/{id}/update             (tasks.edit)
POST /tasks/{id}/complete           (tasks.complete)
POST /tasks/{id}/reopen             (tasks.complete)
POST /tasks/{id}/archive            (tasks.edit)
POST /tasks/{id}/restore            (tasks.edit)

# Rotas contextuais
GET  /companies/{id}/tasks/create
GET  /contacts/{id}/tasks/create
GET  /opportunities/{id}/tasks/create
```

### Como usar

- **Acessar:** menu **Tarefas** (`/tasks`) — visível apenas para quem tem `tasks.view`.
- **Cadastrar:** `/tasks/create`. Título obrigatório (mín. 3 caracteres); tipo,
  prioridade e status validados contra as listas controladas.
- **Vinculada a empresa:** `/tasks/create?company_id={id}` ou `/companies/{id}/tasks/create`.
- **Vinculada a contato:** `/tasks/create?contact_id={id}` ou `/contacts/{id}/tasks/create`.
- **Vinculada a oportunidade:** `/tasks/create?opportunity_id={id}` ou
  `/opportunities/{id}/tasks/create`. Ao vincular uma oportunidade, **empresa e contato
  são preenchidos automaticamente** a partir da oportunidade (quando vazios).
- **Editar:** `/tasks/{id}/edit` (bloqueado se arquivada — exige restaurar antes).
- **Concluir / reabrir:** botões na tela `/tasks/{id}`. Concluir grava
  `completed_at`/`completed_by`; reabrir limpa esses campos e volta para `pendente`.
- **Arquivar / restaurar:** arquivamento lógico (`archived_at`), sem exclusão física.
  Arquivadas saem da listagem padrão; use o filtro **Exibir arquivadas**. O status
  **não é alterado automaticamente** ao arquivar.
- **Dentro de empresa/contato/oportunidade:** os respectivos `show` exibem o bloco
  “Tarefas da …” com contadores (abertas/vencidas), lista resumida das próximas,
  botão **Nova tarefa** (se `tasks.create`) e link **Ver todas** já filtrado.

### Validações de vínculo

- `company_id`/`contact_id`/`opportunity_id`/`assigned_user_id`, se informados, devem existir.
- Se empresa **e** contato forem informados, o contato deve pertencer à empresa.
- Se a oportunidade for informada, valida coerência de empresa/contato relacionados.
- Auto-preenchimento: `opportunity_id` informado preenche `company_id` (empresa da
  oportunidade) e `contact_id` (contato principal da oportunidade) quando estes
  estiverem vazios.

### Logs de auditoria (`activity_logs`)

`task_created`, `task_updated`, `task_status_changed`, `task_completed`,
`task_reopened`, `task_archived`, `task_restored` (entity_type = `task`).

### Checklist de testes (executado — 57/57 PASS)

- [x] `php -l` sem erros em todos os arquivos novos/alterados.
- [x] Migration da Etapa 8 executada sem erro; `tasks.create`/`tasks.edit`/`tasks.complete` sem duplicidade.
- [x] `GET /tasks` sem login → **302** para `/login`.
- [x] `GET /tasks` com `tasks.view` → **200**; sem `tasks.view` → **403**.
- [x] Menu **Tarefas** aparece apenas para quem tem `tasks.view`.
- [x] `GET /tasks/create` sem `tasks.create` → **403**.
- [x] `POST /tasks` com CSRF inválido → **419**.
- [x] Validações: sem título / tipo / prioridade / status inválidos → **422**.
- [x] Empresa, contato ou oportunidade inexistentes → **422**.
- [x] Contato que não pertence à empresa → **422**.
- [x] `POST /tasks` válido → cria e redireciona para o `show`.
- [x] `GET /tasks/{id}` e `/tasks/{id}/edit` → **200**.
- [x] `update` registra `task_updated`; mudança de status registra `task_status_changed`.
- [x] `complete` preenche `completed_at`/`completed_by` e registra `task_completed`.
- [x] `reopen` limpa `completed_at`/`completed_by` e registra `task_reopened`.
- [x] `archive` arquiva sem excluir; some da listagem padrão; **Exibir arquivadas** mostra; `restore` restaura.
- [x] Auto-preenchimento de empresa/contato a partir da oportunidade.
- [x] Filtros (busca, tipo, empresa, responsável, prioridade, status, vencidas, hoje, semana, minhas) e paginação funcionam.
- [x] Tarefa vencida recebe destaque visual.
- [x] Blocos “Tarefas da empresa/contato/oportunidade” aparecem para `tasks.view`; botão **Nova tarefa** só para `tasks.create`.
- [x] Dashboard exibe cards de tarefas (abertas, vencidas, hoje, minhas).
- [x] Identidade visual DCX e ícones Lucide locais preservados.
- [x] Ação POST sem permissão (leitor concluindo tarefa) → **403**.

> **Não foram criados nesta etapa:** Leads do Site, Propostas, Documentos,
> Patrocinadores, Contrapartidas e Relatórios avançados, nem envio real de e-mail,
> notificações por cron ou integração com calendário.
>
> **Próxima etapa: Etapa 9 — Leads do Site**, para receber solicitações vindas das
> páginas públicas de patrocínio.

---

## Etapa 9 — Leads do Site + Integração WordPress (MCP Bia Novamira)

Módulo para receber, armazenar, listar, visualizar, triar e converter leads vindos
dos formulários públicos de patrocínio do site WordPress (`dancacarajas.com.br`) para
empresa, contato, oportunidade e tarefa de follow-up.

### Como rodar a migration da Etapa 9

```bash
docker cp database/migrations/2026_etapa9_leads.sql dcc_db:/tmp/e9.sql
docker exec -e MYSQL_PWD=danca dcc_db sh -c "mariadb -udanca danca_captacao < /tmp/e9.sql"
```

A migration é **idempotente** (`CREATE TABLE IF NOT EXISTS`, permissões com
`ON DUPLICATE KEY UPDATE`). Em instalação nova, `database/schema.sql` já inclui a
tabela `leads`.

> **MariaDB / Hostinger:** o campo `integration_payload` usa **LONGTEXT** (JSON
> serializado pelo PHP), pois alguns planos compartilhados não expõem tipo JSON nativo.

### Estrutura da tabela `leads`

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `name` | VARCHAR(180) NOT NULL | |
| `company_name`, `role_title` | VARCHAR NULL | |
| `email`, `whatsapp` | VARCHAR NULL | WhatsApp normalizado (só dígitos) |
| `city`, `state`, `segment` | VARCHAR NULL | UF com 2 caracteres |
| `origin_page`, `source_url` | VARCHAR NULL | slug e URL completa |
| `form_id`, `form_name` | VARCHAR NULL | identificação do formulário WP |
| `interest`, `message` | VARCHAR/TEXT NULL | |
| `contact_consent` | TINYINT(1) DEFAULT 0 | LGPD |
| `ip_address`, `user_agent`, `referrer` | VARCHAR NULL | metadados da requisição |
| `utm_*` | VARCHAR NULL | campanhas UTM |
| `status` | VARCHAR(40) DEFAULT `novo` | lista controlada |
| `assigned_user_id` | BIGINT NULL | FK `users` |
| `company_id`, `contact_id`, `opportunity_id`, `task_id` | BIGINT NULL | vínculos pós-conversão |
| `integration_payload` | LONGTEXT NULL | JSON bruto sanitizado |
| `converted_at`, `converted_by` | DATETIME/BIGINT NULL | |
| `created_by`, `updated_by` | BIGINT NULL | |
| `created_at`, `updated_at`, `archived_at` | DATETIME | arquivamento lógico |

**Status controlados:** `novo`, `em_triagem`, `convertido_empresa`, `convertido_contato`,
`convertido_oportunidade`, `convertido_tarefa`, `convertido_completo`, `duplicado`,
`descartado`, `respondido`, `aguardando_retorno`, `arquivado`.

### Permissões

- **Já existia:** `leads.view`
- **Criadas:** `leads.create`, `leads.edit`, `leads.convert`, `leads.archive`

| Perfil | Permissões |
|---|---|
| Administrador Geral | todas |
| Captação / Comercial | view, create, edit, convert, archive |
| Produção / Coordenação | apenas view |
| Comunicação | apenas view |
| Leitura / Consulta | apenas view |

### Rotas internas

```
GET  /leads                         (leads.view)
GET  /leads/create                  (leads.create)
POST /leads                         (leads.create + CSRF)
GET  /leads/{id}                    (leads.view)
GET  /leads/{id}/edit               (leads.edit)
POST /leads/{id}/update             (leads.edit + CSRF)
GET  /leads/{id}/convert            (leads.convert)
POST /leads/{id}/convert            (leads.convert + CSRF)
POST /leads/{id}/archive            (leads.archive + CSRF)
POST /leads/{id}/restore            (leads.edit + CSRF)
POST /leads/{id}/mark-duplicate     (leads.edit + CSRF)
POST /leads/{id}/discard            (leads.edit + CSRF)
```

### Endpoint público — `POST /api/leads/site`

**Sem login.** Protegido por token, honeypot e rate limit.

Configuração em `.env`:

```env
LEAD_ENDPOINT_ENABLED=true
LEAD_ENDPOINT_SECRET=trocar-por-token-forte
LEAD_RATE_LIMIT_MINUTES=10
LEAD_RATE_LIMIT_MAX_ATTEMPTS=5
```

| Proteção | Detalhe |
|---|---|
| Token | Header `X-DCF-Lead-Token` ou campo `lead_token` |
| Honeypot | Campos `website_url` ou `website` preenchidos → rejeição silenciosa (201) |
| Rate limit | Arquivos em `storage/ratelimit/{md5(ip)}.json` |
| CORS | Origens permitidas: `dancacarajas.com.br`, localhost dev |

**Resposta de sucesso (201):**

```json
{
  "success": true,
  "message": "Lead recebido com sucesso.",
  "lead_id": 123
}
```

**Exemplo curl (JSON):**

```bash
curl -X POST http://localhost:8080/api/leads/site \
  -H "Content-Type: application/json" \
  -H "X-DCF-Lead-Token: dcf-local-lead-token-2026-trocar-em-producao" \
  -d '{"name":"Maria Silva","empresa":"Empresa X","email":"maria@empresa.com","origin_page":"patrocinio/seja-patrocinador","autorizacao":"1"}'
```

**Exemplo curl (form-urlencoded):**

```bash
curl -X POST http://localhost:8080/api/leads/site \
  -H "X-DCF-Lead-Token: dcf-local-lead-token-2026-trocar-em-producao" \
  -d "nome=João&empresa=Patrocinador SA&email=joao@test.com&origin_page=patrocinio/fale-com-a-producao&autorizacao_contato=1"
```

### Relatório de auditoria WordPress (via MCP Bia Novamira)

**Plugin/form builder:** formulários **HTML customizados** embutidos em `post_content`
(classes `dcx-sponsor-form`, `form-box`, `dcx-fale__form`). **Não** são Elementor Forms,
Contact Form 7, WPForms ou Fluent Forms.

| Página | ID | Formulário? | Observação |
|---|---|---|---|
| `/patrocinio/seja-patrocinador/` | 988493 | Sim | GET → fale-com-a-producao |
| `/patrocinio/cotas-de-patrocinio/` | 988494 | Sim | GET → fale-com-a-producao |
| `/patrocinio/contrapartidas/` | 988495 | Sim | GET → fale-com-a-producao |
| `/patrocinio/lei-rouanet/` | 988496 | Sim | GET → fale-com-a-producao |
| `/patrocinio/impacto-esg-cultural/` | 988499 | Sim | GET → fale-com-a-producao |
| `/patrocinio/marcas-apoiadoras/` | 988500 | Sim | GET → fale-com-a-producao |
| `/patrocinio/relatorios/` | 988502 | Sim | GET → fale-com-a-producao |
| `/patrocinio/fale-com-a-producao/` | 988503 | Sim (2 forms) | form principal + newsletter |

**Páginas sem formulário:** nenhuma — todas as 8 páginas auditadas possuem formulário.

**Achados de segurança/LGPD:**

- Consentimento LGPD presente (`autorizacao`, `autorizacao_contato`, `aceite_contato`, etc.)
- Sem reCAPTCHA/hCaptcha nos forms de patrocínio
- Sem webhook pré-existente
- Honeypot adicionado via integração (`website_url`); newsletter já tinha `website`

### Integração WordPress implantada

Arquivos criados no servidor via MCP Novamira (sandbox):

- `wp-content/novamira-sandbox/dcx-crm-leads-integration.php` — enfileira JS nas páginas de patrocínio
- `wp-content/novamira-sandbox/dcx-crm-leads.js` — intercepta submit, envia JSON ao CRM, preserva UX

Configuração WP (`dcx_crm_leads_settings`):

```php
[
  'enabled'  => '1',
  'endpoint' => 'https://comercial.dancacarajas.com.br/api/leads/site', // URL pública do CRM
  'token'    => 'SEU_LEAD_ENDPOINT_SECRET', // deve coincidir com LEAD_ENDPOINT_SECRET do CRM
]
```

> **Importante:** o WordPress em produção só consegue enviar leads se o CRM estiver
> acessível publicamente na URL configurada. Em desenvolvimento local (`localhost:8080`),
> teste via **curl** ou configure um túnel/deploy temporário.

**Mapeamento de campos por formulário:**

| CRM | Campos WP encontrados |
|---|---|
| `name` | `nome`, `responsavel` |
| `company_name` | `empresa` |
| `role_title` | `cargo` |
| `email` | `email` |
| `whatsapp` | `whatsapp`, `telefone` |
| `city`/`state` | `cidade_uf` (split), `cidade`, `estado` |
| `segment` | `segmento` |
| `interest` | `interesse`, `cota`, `objetivo`, `perfil`, `faixa` |
| `message` | `mensagem`, `valor_estimado`, `regime` |
| `contact_consent` | `autorizacao*`, `aceite_*`, `consent` |

### Conversão de lead

Em `/leads/{id}/convert` o usuário pode criar ou vincular empresa, contato, oportunidade
e tarefa de follow-up. A conversão **nunca é automática** — exige revisão humana.
Status atualizado conforme o que foi criado/vinculado; conversão completa → `convertido_completo`.

### Logs (`activity_logs`)

`lead_received_site`, `lead_created_manual`, `lead_updated`, `lead_archived`, `lead_restored`,
`lead_marked_duplicate`, `lead_discarded`, `lead_converted_*`, `lead_converted_complete`,
`lead_api_rejected`.

### Checklist de validação — sistema (19/19 PASS)

Executado via `php tools/validate_etapa9.php`:

- [x] `php -l` sem erros nos arquivos PHP da Etapa 9.
- [x] Migration executada; permissões `leads.*` criadas.
- [x] Autenticação, listagem, CRUD manual, API pública, honeypot, rate limit, CORS.
- [x] Dashboard com cards de leads novos/triagem/convertidos/descartados.
- [x] Menu **Leads** visível para quem tem `leads.view`.

### Checklist de validação — WordPress

- [x] MCP Bia Novamira conectado; 8 páginas auditadas.
- [x] Plugin sandbox + JS implantados; honeypot e origem configurados.
- [x] Token e endpoint registrados em `dcx_crm_leads_settings`.
- [ ] **Envio E2E live → CRM:** pendente até o CRM estar publicado na URL configurada
  (`comercial.dancacarajas.com.br` ou equivalente). Testes locais do endpoint: **OK via curl**.

> **Próxima etapa: Etapa 9B — Publicação em produção + kit de instalação.**

---

## Etapa 9B — Publicação em produção + Kit de instalação web

Prepara o CRM para implantação inicial na Hostinger com instalador pelo navegador,
schema de produção sem admin padrão e integração WordPress **server-side** (token
nunca no JavaScript público).

### URLs e caminhos (Hostinger)

| Item | Valor |
|---|---|
| CRM | `https://comercial.dancacarajas.com.br` |
| Caminho no servidor | `/home/u482227589/domains/dancacarajas.com.br/public_html/comercial` |
| Document Root ideal | `.../comercial/public` |
| Repositório | `https://github.com/dancacarajas/comercial_dcf` |
| Instalador | `https://comercial.dancacarajas.com.br/install` ou `/install.php` |

### Banco MySQL (preencher senha no instalador)

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u482227589_comercialdcf
DB_USERNAME=u482227589_comercialdcf
DB_PASSWORD=preencher no navegador — não versionar
```

### Arquivos do kit

| Arquivo | Função |
|---|---|
| `app/Controllers/InstallController.php` | Fluxo em 6 etapas |
| `app/Services/InstallerService.php` | .env, schema, admin, lock |
| `app/Views/install/*.php` | Telas do wizard |
| `app/Views/layouts/install.php` | Layout DCX |
| `database/install_schema.sql` | Schema Etapa 9 **sem** admin `Mudar@123` |
| `public/install.php` | Entrada alternativa Hostinger |
| `storage/installed.lock` | Bloqueio pós-instalação (não versionado) |

### Fluxo do instalador

1. **Requisitos** — PHP 8.2+, PDO, pdo_mysql, pastas graváveis, `install_schema.sql`
2. **Banco** — teste PDO + valores sugeridos Hostinger
3. **Sistema** — APP_URL, APP_DEBUG=false, `LEAD_ENDPOINT_SECRET` gerado (`DCF_2026_` + random)
4. **Administrador** — nome, e-mail, senha (mín. 8 caracteres), perfil Administrador Geral
5. **Revisão** — token mascarado; confirmações se `.env` ou tabelas já existirem
6. **Execução** — grava `.env`, importa schema, seed cotas/permissões, cria admin, `installed.lock`
7. **Conclusão** — link `/login`, aviso de bloqueio

### Publicação via SSH (executar pelo usuário)

```bash
ssh -p 65002 u482227589@82.198.236.39
cd /home/u482227589/domains/dancacarajas.com.br/public_html/comercial

# Backup se já houver conteúdo
cd .. && tar -czf comercial_backup_$(date +%Y%m%d_%H%M%S).tar.gz comercial && cd comercial

# Publicar código
git clone https://github.com/dancacarajas/comercial_dcf.git .
# ou: git pull origin main

chmod -R 755 public app config routes database
chmod -R 775 storage
```

Configure no hPanel o Document Root para `public_html/comercial/public` quando possível.

### Pós-instalação — checklist

- [ ] Acessar `/install` e concluir wizard
- [ ] `GET /health` → `{"status":"ok"}` (sem dados sensíveis com APP_DEBUG=false)
- [ ] Login com admin criado no instalador
- [ ] Cotas oficiais presentes (Apresenta, Carajás, Movimento, Formação, Incentivador, Círculo)
- [ ] `POST /api/leads/site` com header `X-DCF-Lead-Token` (token do `.env`)
- [ ] `/install` bloqueado após `storage/installed.lock`
- [ ] Pastas `app/`, `config/`, `storage/`, `database/` → 403/404

### Integração WordPress server-side (v1.1)

| Arquivo WP | Papel |
|---|---|
| `wp-content/novamira-sandbox/dcx-crm-leads-integration.php` | REST `dcx-crm/v1/lead` + `wp_remote_post` ao CRM |
| `wp-content/novamira-sandbox/dcx-crm-leads.js` | Envia ao proxy WP — **sem token** |

Configuração WP (`dcx_crm_leads_settings`):

```php
'endpoint' => 'https://comercial.dancacarajas.com.br/api/leads/site',
'token'    => 'MESMO LEAD_ENDPOINT_SECRET do .env do CRM', // só no servidor WP
```

**Segurança:** View Source, Network e JS público **não** devem expor o token. O navegador
chama apenas `https://dancacarajas.com.br/wp-json/dcx-crm/v1/lead`.

### Teste E2E WordPress → CRM (após CRM live)

Enviar formulário de teste em cada página de patrocínio e confirmar lead em `/leads` com
`origin_page` e `source_url` corretos.

> **Não foram criados:** Propostas, Documentos, Patrocinadores, Contrapartidas, Relatórios avançados.
>
> **Próxima etapa: Propostas (Etapa 10)** — somente após validação desta etapa em produção.

---

## Segurança implementada nesta etapa

- Apenas `/public` acessível pela web; `app/`, `config/`, `routes/`, `storage/` bloqueados via `.htaccess`.
- Sessão segura: cookies `HttpOnly`, `SameSite`, `Secure` (em HTTPS) e regeneração periódica de ID.
- CSRF: token por sessão (`csrf_field()` / `csrf_verify()`), comparação timing-safe.
- Sanitização de saída (`e()`) e de entrada (`clean()`, `input()`), validação (`validate()`).
- Uploads privados em `storage/uploads` com **execução de PHP bloqueada**.
- Logs de erro em `storage/logs/app.log` (display de erros desligado em produção).
- Senhas com `password_hash` / `password_verify` (bcrypt).
- Headers de segurança: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- **Login/logout com CSRF**, regeneração de `session_id` no login e timeout por inatividade (120 min).
- **Proteção contra força bruta** por sessão/IP (5 tentativas → bloqueio de 15 min).
- **Mensagens de erro genéricas** (não revelam se o e-mail existe).
- **Auditoria** em `activity_logs`: `login_success`, `login_failed`, `logout`, `blocked_access_attempt`.
- Pasta `database/` protegida via `.htaccess` (nenhum `.sql` acessível pela web).
- **Ícones Lucide locais** (`/public/assets/vendor/lucide/`), sem dependência de CDN em produção.
- **RBAC:** permissões checadas por rota (`AuthMiddleware::requirePermission()` / `$this->requirePermission()` / `can()`), respondendo **403** quando faltar.
- **Sem exclusão física** de usuários: apenas ativação/inativação por status; auto-inativação do admin logado é bloqueada.
- Auditoria administrativa em `activity_logs`: `user_created`, `user_updated`, `user_activated`, `user_deactivated`, `user_password_reset`, `role_permissions_updated`, `company_created`, `company_updated`, `company_archived`, `company_restored`, `contact_created`, `contact_updated`, `contact_archived`, `contact_restored`, `opportunity_created`, `opportunity_updated`, `opportunity_status_changed`, `opportunity_archived`, `opportunity_restored`, `opportunity_quota_linked`, `quota_created`, `quota_updated`, `quota_archived`, `quota_restored`, `blocked_access_attempt`.
- Hash de senha nunca é exibido em tela; senha provisória mostrada uma única vez ao admin.

---

## Checklist de teste da base instalada

- [ ] `http://seudominio/` exibe **"Dança Carajás Captação — Base instalada"**.
- [ ] O card mostra a versão do PHP (8.2+).
- [ ] O card mostra **"conectado"** na conexão com o banco (após configurar o `.env`).
- [ ] `http://seudominio/health` retorna JSON `{"status":"ok",...}`.
- [ ] `http://seudominio/rota-inexistente` retorna página **404**.
- [ ] Acessar diretamente `http://seudominio/app/Core/App.php` → **403 Forbidden** (ou redirecionado).
- [ ] Acessar diretamente `http://seudominio/config/database.php` → **403 Forbidden**.
- [ ] Acessar diretamente `http://seudominio/storage/logs/app.log` → **403 Forbidden**.
- [ ] `http://seudominio/database/schema.sql` → **403 Forbidden** (SQL não baixa pela web).
- [ ] As 7 tabelas existem no banco: `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `activity_logs`, `system_settings`.
- [ ] A tabela `users` contém o admin inicial e `system_settings` contém `app_name`/`festival_year`.
- [ ] `storage/logs/app.log` é criado e gravável quando ocorre um erro.

### Checklist de autenticação (Etapa 2)
- [ ] `GET /login` → **200** com a identidade visual DCX e ícones Lucide.
- [ ] `POST /login` com CSRF inválido → **bloqueado (419)**.
- [ ] `POST /login` com senha errada → erro genérico *"E-mail ou senha inválidos."*.
- [ ] `POST /login` com senha correta → redireciona para **/dashboard**.
- [ ] `GET /dashboard` sem login → redireciona para **/login**.
- [ ] `GET /dashboard` logado → **200** ("Painel Administrativo").
- [ ] `POST /logout` → encerra a sessão e volta para **/login**.
- [ ] 6 tentativas inválidas seguidas → **bloqueio temporário (429)**.
- [ ] `GET /health` com `APP_DEBUG=false` → apenas `{"status":"ok"}`.

---

## Restrições respeitadas (Hostinger compartilhada)

Sem Docker · Sem Node obrigatório · Sem WebSocket · Sem filas permanentes ·
Sem worker 24h · Sem bibliotecas pesadas · Apenas PHP + MySQL.
