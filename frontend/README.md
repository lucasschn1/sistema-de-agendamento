# ClinicaAme — Frontend

SPA em React que consome a [API do backend](../backend/README.md). Sidebar + área de conteúdo, sem framework de UI além do React Bootstrap.

## Stack

- **React 19** + **Vite** (dev server e build)
- **React Router** (rotas e proteção de rotas autenticadas)
- **React Bootstrap** (componentes de UI — modais, formulários, tabelas)
- **Axios** (cliente HTTP)
- CSS puro em [`src/styles/theme.css`](src/styles/theme.css) — sem Tailwind/styled-components

## Rodando

```bash
npm install
cp .env.example .env   # ajuste VITE_API_URL se o backend não estiver em localhost:8000
npm run dev
```

| Comando | O que faz |
|---|---|
| `npm run dev` | Sobe o servidor de desenvolvimento (porta 5173) |
| `npm run build` | Gera o build de produção em `dist/` |
| `npm run lint` | Roda o ESLint |
| `npm run preview` | Serve o build de produção localmente |

### Variável de ambiente

`VITE_API_URL` — URL base da API (ex: `http://localhost:8000`). Usada em [`src/api/axios.jsx`](src/api/Axios.jsx) como `baseURL` do Axios.

## Como o frontend está organizado

```
src/
├── api/            um arquivo por recurso da API (appointments.js, users.js, ...)
│                   cada função só faz a chamada HTTP e devolve response.data.data
├── components/     componentes genéricos reusados em várias páginas
│   └── layout/     MainLayout, Sidebar, Topbar — moldura das páginas autenticadas
├── context/        AuthContext (usuário logado) e ToastContext (notificações)
├── hooks/          hooks customizados (ex: usePersistedState)
├── pages/          uma pasta por área do sistema (Appointments, Patients, ...)
│                   cada pasta tem a página principal + os modais que ela usa
├── routes/         AppRoutes.jsx — definição de todas as rotas
├── styles/         theme.css — variáveis de cor, layout e overrides do Bootstrap
└── utils/          funções puras (parseApiError, máscaras de CPF/telefone, ...)
```

### Autenticação

`AuthContext` ([`src/context/AuthContext.jsx`](src/context/AuthContext.jsx)) guarda o usuário logado e expõe `login`, `logout`, `isAdmin()`, `isProfessional()`. O token JWT fica no `localStorage`; o interceptor do Axios ([`src/api/axios.jsx`](src/api/Axios.jsx)) injeta o `Authorization: Bearer <token>` em toda requisição e tenta renovar o token automaticamente com `/auth/refresh` quando recebe um 401.

Rotas autenticadas ficam dentro de `MainLayout` em `AppRoutes.jsx`; `PrivateRoute` redireciona pra `/login` se não houver usuário logado, e `PublicRoute` faz o caminho inverso (usuário já logado não acessa `/login`).

### Chamando a API

Nada de `fetch`/`axios` direto dentro das páginas — cada recurso tem seu próprio arquivo em `src/api/` (ex: [`src/api/appointments.js`](src/api/appointments.js)) com uma função por endpoint, já retornando `response.data.data` (o *envelope* `{ success, data }` do backend é desembrulhado ali). As páginas só importam essas funções.

Erros de API são tratados com [`src/utils/apiError.js`](src/utils/apiError.js):
- `parseApiError(err)` → mensagem legível pra mostrar num toast/alert
- `parseApiFieldErrors(err)` → mapa `{ campo: mensagem }` pra erros de validação (422), usado para destacar o campo errado no formulário

### Padrão de cada página

A maioria das páginas em `pages/` segue o mesmo formato:
1. Estado de `loading`/`error` + `useEffect` que busca os dados na API
2. Uma tabela ou lista renderizando esses dados
3. Um ou mais modais (formulário de criar/editar, confirmação de ação destrutiva) controlados por `useState`
4. Ações (`Editar`, `Desativar`, ...) chamando a API e depois recarregando a lista, com feedback via `useToast()`

Exemplo: [`src/pages/Patients/Patients.jsx`](src/pages/Patients/Patients.jsx) + [`src/pages/Patients/PatientFormModal.jsx`](src/pages/Patients/PatientFormModal.jsx).

### Notificações e confirmações

- `useToast()` ([`src/context/ToastContext.jsx`](src/context/ToastContext.jsx)) — dispara um toast de sucesso/erro no canto da tela. Chame `showToast('mensagem')` ou `showToast('mensagem', 'danger')`.
- `<ConfirmModal />` ([`src/components/ConfirmModal.jsx`](src/components/ConfirmModal.jsx)) — modal de confirmação genérico pra ações destrutivas (desativar paciente, etc.).

### Estado que sobrevive à navegação

Alguns filtros (aba ativa, "mostrar inativos") usam `usePersistedState` ([`src/hooks/usePersistedState.js`](src/hooks/usePersistedState.js)) em vez de `useState` puro — funciona igual, mas guarda o valor em `sessionStorage`, então o filtro não reseta ao trocar de página e voltar.

### Tema visual

Todas as cores/espaçamentos ficam em variáveis CSS no topo de [`theme.css`](src/styles/theme.css) (`--brand`, `--color-success`, `--sidebar-width`, etc.). O arquivo também sobrescreve classes do Bootstrap (`.btn-primary`, `.form-check-input:checked`, ...) pra usar a cor da marca em vez do azul padrão — ao estilizar algo novo, prefira reaproveitar essas variáveis a hardcodar cor nova.

## Convenção de rotas do sistema

| Rota | Página | Quem acessa |
|---|---|---|
| `/login`, `/forgot-password` | Login | Público |
| `/dashboard` | Dashboard | Autenticado |
| `/appointments` | Agendamentos | Autenticado (profissional vê só os seus) |
| `/patients` | Pacientes | Autenticado |
| `/procedures` | Procedimentos | Autenticado (leitura) / Admin (escrita) |
| `/financial` | Financeiro | Admin |
| `/users` | Usuários (profissionais/admins) | Admin |
