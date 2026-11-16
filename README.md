# CDP Onboarding API

Backend REST API for the **Vector Onboarding OS** — a candidate onboarding and employee management platform covering recruitment, document verification, interview workflows, organization master data, hierarchy reporting, and bulk data operations.

Built with **Laravel 12** (PHP ^8.2), JWT authentication, and Spatie role/permission-based RBAC.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Getting Started](#getting-started)
- [Environment Configuration](#environment-configuration)
- [API Overview](#api-overview)
- [Authentication & Permissions](#authentication--permissions)
- [Core Workflows](#core-workflows)
- [Bulk Import](#bulk-import)
- [Data Export](#data-export)
- [Reports](#reports)
- [Testing](#testing)
- [Code Style](#code-style)
- [Project Structure](#project-structure)
- [Security Notes](#security-notes)
- [Postman Collection](#postman-collection)

---

## Features

- **Candidate lifecycle workflow** — CV submission → document upload & verification → 1st interview → 2nd interview → final approval/rejection, with a full status enum, stage pools, and terminal-state handling.
- **Public candidate portal** — unauthenticated, API-key-guarded CV submission plus a token + SMS-OTP document upload portal.
- **Employee management** — org hierarchy (`province → zonal → region → branch → department → designation`), self-referencing reporting manager, manager reassignment with effective-dated history.
- **Channel-wise employment** — channel heads with assignment history and channel-scoped hierarchy reports.
- **Master data CRUD** — countries, provinces, zonals, regions, groups, branches, departments, designations, document types.
- **Access control** — 93 granular permissions grouped by module, assigned via roles (Super Admin seeded).
- **Notifications** — database notifications for candidate stage changes, interview assignments, manager reassignments; plus email and SMS (Dialog eSMS gateway).
- **Bulk import** — config-driven CSV import for all master-data and employee tables, with partial-update support and error reporting.
- **Data export** — table-by-table JSON export and full SQL dump export.
- **Audit trail** — activity logging (DB + Laravel log) for mutations and failed actions.
- **Dashboard** — user-hierarchy-scoped candidate metrics.

---

## Tech Stack

| Component | Choice |
|---|---|
| Framework | Laravel 12, PHP ^8.2 |
| Auth | `php-open-source-saver/jwt-auth` (JWT, guard `api`) |
| RBAC | `spatie/laravel-permission` |
| SMS | Dialog eSMS gateway |
| Email | Laravel mail (SES-capable) |
| SSO | Socialite + Google provider (prepared) |
| Database | MySQL / MariaDB (SQLite in-memory for tests) |
| Testing | Pest v4 |
| API docs | Postman collection (`api-docs/CDP Vector API.json`) |

---

## Architecture

- **Versioned API** — all business endpoints under `/api/v1` (`routes/v1.php`), external integrations under `/api/v1/portal/*` (`routes/external.php`, no JWT).
- **Controller → FormRequest → Service/Model** flow; ~35 FormRequest classes handle validation with a consistent error envelope `{message, errors: [{field, messages}]}`.
- **Standard response envelope** — `{status, message, data}`; paginated lists return a paginator object.
- **Middleware** — global API group applies `ForceHttps`, `SanitizeInput`, `SecurityHeaders`, and `throttle:60,1`; login is throttled at 5/min; portal endpoints use a shared-secret key header.
- **Hierarchy visibility** — candidates and employees are filtered by channel/branch scope via model scopes (`accessibleBy`), not just permissions.
- **Activity logging** — `ActivityLogTrait` writes structured audit records (DB + file) on important actions and failures.

---

## Getting Started

### Prerequisites

- PHP >= 8.2 with extensions required by Laravel
- Composer
- MySQL or MariaDB (production/dev DB)
- Node.js + npm (for Vite assets, if needed)

### Installation

```bash
git clone <repo-url>
cd cdp-onboarding-api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configure your database credentials in `.env`, then:

```bash
php artisan migrate
php artisan db:seed          # PermissionsSeeder + UserSeeder
php artisan serve
```

Seeded dev admin: **`devadmin`** (or `dev@localhost.com`) with password **`password`** and the `Super Admin` role. Change these immediately in any shared environment.

Health checks: `GET /health` (DB-aware), `GET /api/health-check`, `GET /up`.

---

## Environment Configuration

Key variables (full list in `.env.example`):

| Group | Variables |
|---|---|
| App | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| JWT | `JWT_SECRET`, `JWT_ALGO`, `JWT_TTL`, `JWT_REFRESH_TTL` |
| CORS | `CORS_ALLOWED_ORIGINS`, `CORS_SUPPORTS_CREDENTIALS` |
| Portal | `PORTAL_API_KEY` — sent as `X-CDP-PORTAL-KEY` header on public portal endpoints |
| Frontend | `FRONTEND_URL` — used in email links |
| SMS | `DIALOG_SMS_URL`, `DIALOG_SMS_USERNAME`, `DIALOG_SMS_PASSWORD`, `DIALOG_SMS_MASK` |
| Google SSO | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` |
| Mail | `MAIL_*`, optional `AWS_*` for SES |

---

## API Overview

Base URL: `http://localhost:8000/api/v1`

### Public (no JWT)

| Method | Endpoint | Description |
|---|---|---|
| POST | `/login` | Login with username, email or ID number (`throttle:5,1`) |
| POST | `/portal/candidates` | Public CV submission (requires `X-CDP-PORTAL-KEY`) |
| GET | `/portal/candidates/validate/{token}` | Validate document upload token |
| POST | `/portal/candidates/request-otp` | Request SMS OTP (5/min) |
| POST | `/portal/candidates/verify-otp` | Verify OTP (5/min) |
| GET | `/portal/candidates/checklist/{token}` | Required document checklist |
| POST | `/portal/candidates/upload` | Upload a document |
| POST | `/portal/candidates/submit-all` | Finalize submission |

### Protected (`auth:api` JWT)

| Module | Endpoints |
|---|---|
| Auth | `POST /logout`, `GET /me` |
| Dashboard | `GET /dashboard` |
| Users / Roles / Permissions | full `apiResource` + `GET {module}/list`, `PATCH users/{id}/toggle-status`, `PATCH users/complete-tour` |
| Master data | `countries`, `provinces`, `zonals`, `regions`, `groups`, `branches`, `departments`, `designations`, `document-types`, `channel-wise-employments` — each with `list`, `toggle-status`, and `apiResource` |
| Channel heads | `GET/POST channel-wise-employments/{id}/head`, `GET .../head/history` |
| Employees | `GET employees/{id}/manager-history`, `POST employees/{id}/reassign-manager`, `GET employees/{id}/hierarchy-as-of` |
| Candidates | full `apiResource` + workflow endpoints (see below) |
| Notifications | index, `unread-count`, `read`, `mark-all-read`, delete |
| Activity logs | index, show |
| Database | `GET /database/export`, `export-data`, `export-table/{table}`, `export-all-tables` |
| Import | `GET /import/tables`, `catalog`, `GET /import/template/{table}`, `POST /import/{table}` |
| Reports | `GET /reports/channel-wise-hierarchy`, `GET /reports/employee-hierarchy` |

### Candidate workflow endpoints

```
POST /candidates/{id}/documents/verify
POST /candidates/{id}/documents/reject          # with re-upload link
POST /candidates/{id}/documents/reject-fully    # terminal, no re-upload
POST /candidates/{id}/resend-document-link
POST /candidates/{id}/assign-1st-interview
POST /candidates/{id}/approve-1st-interview     # also POST /reject-1st-interview
POST /candidates/{id}/assign-2nd-interview
POST /candidates/{id}/approve-2nd-interview     # also POST /reject-2nd-interview
POST /candidates/{id}/approve-final             # also POST /reject-final
GET  /candidates/list, /candidates/{id}/approvals, /candidates/{id}/documents
```

---

## Authentication & Permissions

1. **Login** — `POST /login {login, password}` returns a JWT; send it as `Authorization: Bearer {token}`.
2. **RBAC** — Spatie permissions with guard `api`; controllers declare middleware via `Middleware::permission(...)` per action group. The seeder provisions 93 permissions across modules (Access Management, Users, Geography, Branches, Departments, Document Types, Channel-wise, Candidates/interview stages, Employee Hierarchy, Channel Heads, Notifications, Database, Import, Dashboard, Reports) and assigns all to `Super Admin`.
3. **Hierarchy scoping** — beyond permissions, candidate/employee visibility is narrowed by channel and branch assignment (`accessibleBy` scope).

Errors: `401` invalid/expired token, `403` missing permission or out-of-scope record, `422` validation, `429` rate limit.

---

## Core Workflows

### Candidate onboarding

```
pending
  └─ document_submitted ─ document_verified ─ 1st_interview ─ 1st_interview_passed
                                    │                │            └─ 1st_interview_rejected (terminal)
                                    │                └─ 2nd_interview ─ 2nd_interview_passed
                                    │                                  └─ 2nd_interview_rejected (terminal)
                                    ├─ document_rejected            (re-upload link sent)
                                    └─ document_fully_rejected      (terminal, no re-upload)
                                                           └─ final_approved (terminal)
```

- Candidates are auto-assigned a `candidate_code` (`DIR`/`R` prefix + yymm + sequence) and a `document_upload_token` for the portal.
- Interview assignments, approvals and rejections are audited in `interview_approvals` and trigger notifications to the uploader.

### Employee hierarchy

- Employees belong to the org/territory tree and self-reference via `reporting_manager_id`.
- Manager reassignments are effective-dated (`employee_hierarchy_histories`) and support point-in-time queries (`GET employees/{id}/hierarchy-as-of`).
- `GET /reports/employee-hierarchy` returns the nested tree with branch/designation/channel filters.

---

## Bulk Import

`POST /import/{table}` accepts a CSV (max 10 MB) for: `countries`, `provinces`, `zonals`, `regions`, `groups`, `branches`, `departments`, `designations`, `channel_wise_employments`, `document_types`, `employees`, `candidates`, `users`.

Behavior (config-driven in `app/Services/BulkImportService.php`):

- Rows are upserted on the table's **unique key** (e.g. `employee_code` for employees, `username` for users); an `id` column also works as a fallback match key.
- **Partial updates** — empty cells leave existing values untouched, so e.g. a two-column `id,reporting_manager_id` file updates only managers.
- **Flexible references** — dependencies are validated by DB id; `reporting_manager_id` also accepts an `employee_code`, and self-references are resolved in a second pass so manager links resolve regardless of row order.
- Per-row transactions: a failed row rolls back individually; the response reports `{total, imported, failed, errors: [{row, error}]}`.
- Templates: `GET /import/template/{table}` downloads a CSV template with headers and a sample row.

---

## Data Export

- `GET /database/export` — full SQL dump (mysqldump-style, MySQL/MariaDB).
- `GET /database/export-table/{table}` / `export-all-tables` — structured table data (system tables excluded).

---

## Reports

- `GET /reports/channel-wise-hierarchy` — channel head → employees tree, scoped to the current channel head.
- `GET /reports/employee-hierarchy` — org hierarchy tree with branch/designation/channel filters, employee search, and inactive-employee toggling.

---

## Testing

```bash
composer test          # or: ./vendor/bin/pest
```

- **Pest v4**, SQLite `:memory:` with `RefreshDatabase`.
- Tests authenticate with a real JWT (`JWTAuth::fromUser($user)`) — no mocking of the auth layer.
- `PermissionsSeeder` runs inside tests to establish RBAC.

---

## Code Style

```bash
./vendor/bin/pint      # Laravel Pint (PSR-12)
```

---

## Project Structure

```
app/
├── Enums/                  # CandidateStatus and workflow states
├── Http/
│   ├── Controllers/V1/     # Versioned API + External/ portal controllers
│   └── Requests/           # FormRequest validation (~35)
├── Models/                 # 20 models incl. Candidate, Employee, ActivityLog
├── Notifications/          # Candidate stage / assignment notifications
├── Services/               # BulkImportService, DashboardService, SmsService,
│                           # DatabaseService, DataExportService
└── Traits/                 # ActivityLogTrait, CandidateSmsTrait, FileUploadTrait
routes/
├── v1.php                  # Authenticated API
├── external.php            # Public portal endpoints
└── api.php                 # Health check + registration of the above
database/
├── migrations/
└── seeders/                # PermissionsSeeder (93 perms), UserSeeder
tests/Feature/              # Pest feature tests (workflow, import, permissions)
api-docs/                   # Postman collection
```

---

## Security Notes

- All API traffic passes through `ForceHttps`, `SanitizeInput`, and `SecurityHeaders` middleware.
- Portal endpoints are protected by a shared secret header and rate limiting; OTPs are cached with short lifetimes.
- CORS is restricted to `CORS_ALLOWED_ORIGINS`.
- Never commit `.env`, JWT secrets, or portal keys; rotate `JWT_SECRET` and `PORTAL_API_KEY` on team changes.

---

## Postman Collection

Import `api-docs/CDP Vector API.json` into Postman. Set `baseUrl` (default `http://localhost:8000/api/v1`) and capture `auth_token` from the login response.
