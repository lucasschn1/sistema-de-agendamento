# ClinicaAme API

API REST em PHP para o sistema de agendamento da clínica (psicólogos, psicopedagogos e outros profissionais). Gerencia usuários, agendamentos (incluindo recorrências), procedimentos/serviços e financeiro.

## Stack

- PHP 8.2+ (sem framework — router e DI containers próprios)
- MySQL
- JWT (`firebase/php-jwt`) para autenticação
- `vlucas/phpdotenv` para variáveis de ambiente
- PHPUnit para testes

## Requisitos

- PHP >= 8.2 com extensão PDO MySQL
- MySQL 8+
- Composer

## Setup

```bash
composer install
cp .env.example .env   # ajuste as variáveis (veja abaixo)
```

Crie o banco e as tabelas a partir do schema mais recente:

```bash
mysql -u root -p < database/schema2.sql
```

> `database/schema2.sql` é o schema atual (soft delete em todas as tabelas, tabela dedicada `recurrence_groups`, trigger de conflito de horário). `database/schema.sql` é uma versão antiga, mantida só de referência.

### Variáveis de ambiente (`.env`)

| Variável | Descrição |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexão MySQL |
| `APP_ENV` | `development` ou `production` |
| `APP_DEBUG` | Em `true`, erros não tratados retornam mensagem + arquivo/linha |
| `APP_URL` | URL base da API |
| `APP_TIMEZONE` | Timezone usado em toda a aplicação (ex: `America/Sao_Paulo`) |
| `JWT_SECRET` | Segredo para assinar os tokens — **troque em produção** |
| `JWT_EXPIRATION` | Validade do access token em segundos |
| `JWT_ALGORITHM` | Algoritmo de assinatura (`HS256`) |
| `BCRYPT_COST` | Custo do hash de senha |
| `FRONTEND_URL` | Origem liberada no CORS (deve bater com a porta em que o frontend roda) |

### Rodando

```bash
php -S localhost:8000 -t public
```

### Testes

```bash
composer test            # roda a suíte com saída legível (--testdox)
composer test-coverage   # gera relatório de cobertura em coverage/
```

## Autenticação

A API usa JWT via header `Authorization: Bearer <token>`.

1. `POST /auth/login` retorna `access_token` (curta duração, `JWT_EXPIRATION`) e `refresh_token` (30 dias).
2. Envie o `access_token` em todas as rotas protegidas.
3. Quando expirar, troque por um novo com `POST /auth/refresh`.

Existem três papéis (`role`): `admin`, `professional`, `patient`. Pacientes ainda não têm login próprio (ver seção "Futuro" em `src/Config/routes.php`).

## Formato de resposta

Toda resposta é JSON. Sucesso:

```json
{
  "success": true,
  "data": { },
  "message": "opcional"
}
```

Erro:

```json
{
  "success": false,
  "error": {
    "type": "ValidationException",
    "message": "Erro de validação",
    "errors": { "campo": "mensagem" }
  }
}
```

`errors` só aparece em erros de validação (422). Códigos HTTP usados: `200`, `201`, `204`, `400`, `401`, `403`, `404`, `409`, `422`, `500`.

## Permissões por rota

- **Pública** — sem token.
- **Autenticado** — qualquer usuário logado (`admin` ou `professional`).
- **Admin** — exige `role = admin` (`RoleMiddleware`).

Nas tabelas abaixo, a coluna **Acesso** indica isso.

---

## Referência da API

### Auth (pública)

| Método | Rota | Descrição |
|---|---|---|
| POST | `/auth/login` | `{ email, password }` → tokens + dados do usuário |
| POST | `/auth/refresh` | `{ refresh_token }` → novo `access_token` |

### Perfil do usuário logado (autenticado)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/me` | Dados do usuário autenticado |
| PUT | `/api/me` | Atualiza `name`, `phone`, `bio`, `specialty` |
| PATCH | `/api/me/password` | `{ current_password, new_password }` |

### Agendamentos (autenticado — profissional só vê os próprios)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/appointments` | Lista. Query: `professional_id`, `patient_id`, `status`, `start`, `end`. Profissional sempre vê só os seus (ignora filtros de data); admin pode filtrar por período |
| GET | `/api/appointments/{id}` | Detalhe com paciente/profissional/serviço |
| POST | `/api/appointments` | `{ patient_id, professional_id, service_id, start_time, notes? }` |
| PUT | `/api/appointments/{id}` | Atualiza `notes` e/ou `price` (não altera horário) |
| PATCH | `/api/appointments/{id}/confirm` | `scheduled` → `confirmed` |
| PATCH | `/api/appointments/{id}/complete` | → `completed` |
| PATCH | `/api/appointments/{id}/cancel` | `{ reason }` (obrigatório) → `cancelled` |
| PATCH | `/api/appointments/{id}/no-show` | `{ reason? }` → `no_show` |
| PATCH | `/api/appointments/{id}/reschedule` | `{ start_time }` — só para status `scheduled`/`confirmed` |
| POST | `/api/appointments/recurrence` | Cria uma série recorrente (ver abaixo) |
| GET | `/api/appointments/recurrence/{groupId}` | Lista todas as sessões de uma recorrência |
| PATCH | `/api/appointments/recurrence/{groupId}/cancel` | `{ reason, from_date? }` — cancela a sessão e todas as futuras (padrão: a partir de hoje) |
| GET | `/api/availability` | Query: `professional_id`, `date`, `duration?`, `exclude_id?` → `{ available: bool }` |
| DELETE | `/api/appointments/{id}` | **Admin.** Soft delete |
| PATCH | `/api/appointments/{id}/restore` | **Admin.** Restaura soft delete |

**Criar recorrência** — `POST /api/appointments/recurrence`:

```json
{
  "patient_id": 3,
  "professional_id": 1,
  "service_id": 1,
  "type": "semanal",       // "semanal" | "quinzenal"
  "day_of_week": 4,        // 0 (domingo) a 6 (sábado)
  "start_hour": "09:00:00",
  "start_date": "2026-07-16",
  "end_date": "2026-12-31", // opcional — sem isso, gera sessões por até 2 anos
  "notes": "opcional"
}
```

Status possíveis de um agendamento: `scheduled`, `confirmed`, `completed`, `cancelled`, `no_show`.

### Pacientes e profissionais (autenticado para leitura; escrita é admin)

| Método | Rota | Acesso | Descrição |
|---|---|---|---|
| GET | `/api/users` | Admin | Query: `role` (`patient`\|`professional`\|`admin`, omitido = pacientes+profissionais), `active` (`true`\|`false`) |
| GET | `/api/users/search` | Admin | Query: `name` (busca parcial) ou `type` (filtra profissionais por `professional_type`) |
| GET | `/api/users/stats` | Admin | Contadores por tipo de usuário |
| GET | `/api/users/{id}` | Admin | Detalhe |
| POST | `/api/users/patient` | Admin | `{ name, email, password, cpf?, phone?, birthdate? }` |
| POST | `/api/users/professional` | Admin | `{ name, email, password, professional_type, council_id?, specialty?, bio?, cpf?, phone? }` |
| POST | `/api/users/admin` | Admin | `{ name, email, password }` |
| PUT | `/api/users/{id}` | Admin | Atualiza dados de qualquer usuário |
| PATCH | `/api/users/{id}/deactivate` | Admin | Soft delete — bloqueado se houver agendamentos futuros ativos |
| PATCH | `/api/users/{id}/restore` | Admin | Reativa |
| PATCH | `/api/users/{id}/reset-password` | Admin | `{ new_password }` — redefine sem exigir a senha atual |

Senha mínima: 6 caracteres.

### Procedimentos / serviços

| Método | Rota | Acesso | Descrição |
|---|---|---|---|
| GET | `/api/procedures` | Autenticado | Query: `category`, `active` (`true`\|`false`, padrão `true`), `search` |
| GET | `/api/procedures/{id}` | Autenticado | Detalhe |
| GET | `/api/procedures/categories` | Admin | Lista de categorias em uso |
| GET | `/api/procedures/stats` | Admin | Estatísticas e procedimentos mais usados |
| POST | `/api/procedures` | Admin | `{ name, description?, price, duration_minutes, category }` |
| PUT | `/api/procedures/{id}` | Admin | Atualiza qualquer campo acima |
| PATCH | `/api/procedures/{id}/price` | Admin | `{ price }` |
| PATCH | `/api/procedures/{id}/activate` | Admin | |
| PATCH | `/api/procedures/{id}/deactivate` | Admin | Bloqueado se houver recorrências ativas usando o procedimento |
| DELETE | `/api/procedures/{id}` | Admin | Soft delete (equivalente a desativar) |

Categorias usadas pelo frontend: `Individual`, `Casal`, `Familiar`, `Grupo`, `Avaliação`.

### Financeiro (admin)

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/financial/payment` | `{ appointment_id, method, date? }` — `date` padrão hoje |
| PATCH | `/api/financial/payment/{id}/undo` | `{ reason }` — estorna o pagamento |
| GET | `/api/financial/pending` | Agendamentos realizados com pagamento pendente |
| GET | `/api/financial/summary?start=&end=` | Resumo do período: receita bruta, recebido, pendente, contagem por status, por método |
| GET | `/api/financial/summary/month?year=&month=` | Resumo de um mês específico |
| GET | `/api/financial/summary/current` | Atalho para o mês atual |
| GET | `/api/financial/paid?start=&end=&method=` | Extrato de agendamentos pagos no período |
| GET | `/api/financial/methods` | Métodos de pagamento aceitos: `PIX`, `Dinheiro`, `Cartão de Crédito`, `Cartão de Débito`, `Transferência` |

## Estrutura do projeto

```
public/index.php        Ponto de entrada — bootstrap, CORS, dispatch
src/Config/              bootstrap, dependências (DI container manual), routes.php
src/Core/                Router, Request, Response, Database
src/Middleware/          AuthMiddleware, RoleMiddleware
src/Controllers/         Um por recurso (Auth, User, Appointment, Procedure, Financial)
src/Services/            Regras de negócio e validação
src/Repositories/        Acesso a dados (PDO)
src/Models/              Entidades (User, Appointment, Service)
src/Exceptions/          Exceções de negócio, organizadas por domínio
database/schema2.sql     Schema atual do banco
tests/                   Testes PHPUnit (Services e Repositories)
```

### Convenção de rotas

O `Router` casa rotas na **ordem em que são registradas** em `routes.php` — uma rota com `{id}` genérico intercepta qualquer segmento, então rotas literais (`/search`, `/categories`, `/stats`) precisam ser declaradas **antes** da rota `{id}` correspondente.
