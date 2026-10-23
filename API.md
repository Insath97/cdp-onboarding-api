# VECTOR Onboarding API — Full Documentation

Base URL: `http(s)://<host>/api/v1`

---

## 1. Conventions

### Authentication
- JWT (`PHPOpenSourceSaver/JWTAuth`), guard `api`
- Public: `POST /login` only. All other endpoints require header:
  ```
  Authorization: Bearer <token>
  ```
- Missing/invalid token → `401`

### Success envelope
```json
{ "status": "success", "message": "...", "data": { } }
```
List endpoints return a Laravel paginator inside `data`:
```json
{ "status": "success", "message": "...", "data": { "data": [...], "current_page": 1, "total": 100, "per_page": 15 } }
```

### Error envelopes
| Code | Shape |
|---|---|
| 401 | `{ "message": "Unauthenticated.", "error": "Token not provided or invalid" }` |
| 403 | `{ "message": "This action is unauthorized.", "error": "..." }` (missing permission or record access) |
| 404 | `{ "status": "error", "message": "…not found" }` |
| 422 (FormRequest) | `{ "message": "…", "errors": [ { "field": "name", "messages": ["…"] } ] }` |

### Pagination
`?page=N&per_page=N` (candidates capped at 100).

### File uploads
`multipart/form-data`; CVs: `pdf|doc|docx`, max 5 MB.

### Security middleware
All API requests pass `ForceHttps`, `SanitizeInput`, `SecurityHeaders`, and rate limiting (`60/min`).

---

## 2. Authentication

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| POST | `/login` | Public | Throttled **5/min** |
| POST | `/logout` | JWT | Revokes token |
| GET | `/me` | JWT | Current user + roles + permissions |

**Login** — `login` can be username, email, or employee `id_number`:
```json
POST /api/v1/login
{ "login": "john@example.com", "password": "secret" }
```
→ `200` with `{ access_token, token_type: "bearer", expires_in, user }` or `401 "Invalid credentials"`.

New users default password: their NIC/`id_number`.

---

## 3. RBAC Model

Spatie Laravel Permission, guard `api`. Each controller enforces permissions via `HasMiddleware` + `permission:` middleware. Super Admin role holds all permissions.

### Candidate permission matrix

| Endpoint | Method | Permission(s) (any-of) |
|---|---|---|
| `/candidates` | GET (index) | `Candidate Index`, `Candidate Show`, `Candidate Assign/Approve 1st`, `Candidate Assign/Approve 2nd`, `Candidate Approve Final`, **`Candidate 2nd Interview List`**, **`Candidate Assigned List`** |
| `/candidates/list` | GET | same as index |
| `/candidates/{id}` | GET (show) | `Candidate Index`, `Candidate Show`, `Candidate Assign/Approve 1st`, `Candidate Assign/Approve 2nd`, `Candidate Approve Final` |
| `/candidates/{id}/approvals` | GET | same as show |
| `/candidates/interviewers` | GET | same as show |
| `/candidates` | POST | `Candidate Create` |
| `/candidates/{id}` | PUT/PATCH | `Candidate Update` |
| `/candidates/{id}` | DELETE | `Candidate Delete` |
| `/candidates/{id}/assign-1st-interview` | POST | `Candidate Assign 1st Interview` |
| `/candidates/{id}/approve-1st-interview` | POST | `Candidate Approve 1st Interview` |
| `/candidates/{id}/reject-1st-interview` | POST | `Candidate Reject 1st Interview` |
| `/candidates/{id}/assign-2nd-interview` | POST | `Candidate Assign 2nd Interview` |
| `/candidates/{id}/approve-2nd-interview` | POST | `Candidate Approve 2nd Interview` |
| `/candidates/{id}/reject-2nd-interview` | POST | `Candidate Reject 2nd Interview` |
| `/candidates/{id}/approve-final` | POST | `Candidate Approve Final` |
| `/candidates/{id}/reject-final` | POST | `Candidate Reject Final` |

The two **list-only** permissions (`Candidate 2nd Interview List`, `Candidate Assigned List`) grant the list endpoints only — no show, no actions.

### Full permission catalog

| Group | Permissions |
|---|---|
| Access Management | `Permission Index/Create/Update/Delete`, `Role Index/Create/Update/Delete` |
| User Management | `User Index/Create/Update/Delete/Toggle Status` |
| Country / Province / Zonal / Region / Branch / Department / Designation / Group Management | `{Entity} Index/Create/Update/Delete/Toggle Status` (8 entities) |
| Channel Wise Employment Management | `… Index/Create/Update/Delete/Toggle Status`, plus `Channel Head Index/Assign` |
| Candidate Management | `Candidate Index`, `Create`, `Show`, `Update`, `Delete`, `Assign/Approve/Reject 1st Interview`, `Assign/Approve/Reject 2nd Interview`, **`Candidate 2nd Interview List`**, **`Candidate Assigned List`**, `Approve/Reject Final` |
| Employee Hierarchy | `Employee Hierarchy View`, `Employee Hierarchy Reassign` |
| Activity Log | `Activity Log Index` |
| Notifications | `Notification Index`, `Notification Mark Read` |
| Database Management | `Database Export` |
| Import Management | `Import Index`, `Bulk Import` |
| Dashboard | `Dashboard View` |

---

## 4. Candidate Module

### 4.1 Workflow state machine

```
pending ──assign-1st──▶ 1st_interview ──approve-1st──▶ 1st_interview_passed
                          └─reject-1st──▶ 1st_interview_rejected (terminal)

1st_interview_passed ──assign-2nd──▶ 2nd_interview ──approve-2nd──▶ 2nd_interview_passed
                        (re-assign)    └─reject-2nd──▶ 2nd_interview_rejected (terminal)

2nd_interview_passed ──approve-final──▶ final_approved (terminal)
                        └─reject-final──▶ rejected (terminal)
```

Status preconditions enforced (`422` otherwise):

| Action | Allowed from | Resulting status |
|---|---|---|
| assign 1st | `pending`, `1st_interview` | `1st_interview` |
| approve 1st | `pending`, `1st_interview` | `1st_interview_passed` |
| reject 1st | `pending`, `1st_interview` | `1st_interview_rejected` |
| assign 2nd | `1st_interview_passed`, `2nd_interview` | `2nd_interview` |
| approve 2nd | `2nd_interview` only | `2nd_interview_passed` |
| reject 2nd | `2nd_interview` only | `2nd_interview_rejected` |
| approve final | `2nd_interview_passed` | `final_approved` |
| reject final | `2nd_interview_passed` | `rejected` |

Every transition writes an `interview_approvals` audit row (`stage`, `action=assigned|approved|rejected`, actor, assignee, dates, comments) and fires notifications (assignment → assignee; decision → candidate uploader).

### 4.2 Record visibility (`accessibleBy`)

A non-Super-Admin sees a candidate when **any** of:

1. **Uploader hierarchy** — they uploaded it, or someone below them in the reporting-manager chain did
2. **Channel head** — they are the snapshotted channel head
3. **Assigned** — an `interview_approvals` row has `assigned_to_user_id = them`
4. **2nd-interview pool manager** — holds `Candidate Assign 2nd Interview` → sees statuses `1st_interview_passed`, `2nd_interview`, `2nd_interview_passed`, `2nd_interview_rejected`, `final_approved`, `rejected` (cross-channel)
5. **2nd-interview list viewer** — holds `Candidate 2nd Interview List` → sees only `1st_interview_passed`, `2nd_interview`, `2nd_interview_rejected` (cross-channel, no actions)
6. **Final approval** — holds `Candidate Approve/Reject Final` → sees `2nd_interview_passed`, `final_approved`, `rejected` (cross-channel)

### 4.3 Visibility scenario matrix

| Persona | pending / 1st stage | 1st_interview_passed (2nd pool) | 2nd_interview (assigned to X) | 2nd_interview_passed+ |
|---|---|---|---|---|
| Super Admin | all | all | all | all |
| Uploader / managers above uploader | own uploads | own | own | own |
| Channel head | own channel | own channel | own channel | own channel |
| Assigned interviewer (any stage) | when assigned | when assigned | **X only** | keeps record access |
| `Candidate Assign 2nd Interview` | no (unless rule 1-3) | **yes, cross-channel** | **yes** | **yes** |
| `Candidate 2nd Interview List` | no | **yes (list only)** | **yes (list only)** | no |
| `Candidate Assigned List` | only when assigned | only when assigned | only when assigned | only when assigned |
| `Candidate Approve/Reject 2nd` | no | **no** (must be assigned) | only X can act; others no access | record access if assigned |
| `Candidate Approve/Reject Final` | no | no | no | **yes, cross-channel** |

**CV access** — `cv_url` (30-min temporary URL, private disk) is embedded in every candidate serialization. Anyone who can load the record can open the CV. List-only personas get the CV only via the list payload.

**2nd-interview action gate** — approve/reject 2nd requires: permission + record access + status exactly `2nd_interview` + being the **latest assigned interviewer** (Super Admin bypasses the last check). Unassigned holders get `403`.

### 4.4 Endpoints

**GET `/candidates`** — paginated, access-scoped.
Query: `search` (name/NIC/phone), `status` (single or comma list), `stage` (`1st_interview|2nd_interview|final_approval`), `designation_id`, `page`, `per_page`.

**GET `/candidates/list`** — lightweight select-list (same scoping/filters), returns `id, first_name, last_name, nic_number, designation_id, status`.

**GET `/candidates/interviewers?stage=…`** — eligible interviewers (active users holding the stage's Approve permission, or Super Admin); `search` supported.

**GET `/candidates/{id}/approvals`** — full audit trail.

**POST `/candidates`** (multipart) — `first_name`, `last_name`, `nic_number`, `phone_number`, `secondary_number?`, `designation_id`, `expected_salary`, `cv_file` (required), `uploader_comments?`. Snapshots the channel head. Notifies channel head.

**PUT/PATCH `/candidates/{id}`** — same fields (all optional); `cv_file` replaces CV.

**Assign / approve / reject** — bodies:

```json
// assign 1st / assign 2nd
{ "assigned_to_user_id": 12, "assigned_date": "2026-10-23", "comments": "…" }

// approve / reject (1st, 2nd, final)
{ "interview_date": "2026-10-23", "comments": "…" }
```
Dates: `Y-m-d`. Comments: max 1000 chars. Common failures: `403` (no permission / not assigned / not accessible), `422` (wrong status or validation).

---

## 5. Org Structure Modules

Standard `apiResource` CRUD + extras. All are permission-gated per section 3; all support `?search=` and paginator params on index.

| Module | Base path | Extras |
|---|---|---|
| Countries | `/countries` | `GET /list` (active), `PATCH /{id}/toggle-status` |
| Provinces | `/provinces` | `GET /list`, `PATCH /{id}/toggle-status` |
| Zonals | `/zonals` | `GET /list`, `PATCH /{id}/toggle-status` |
| Regions | `/regions` | `GET /list`, `PATCH /{id}/toggle-status` |
| Branches | `/branches` | `GET /list`, `PATCH /{id}/toggle-status` |
| Departments | `/departments` | `GET /{id}/designations`, `PATCH /{id}/toggle-status` |
| Designations | `/designations` | `GET /list`, `PATCH /{id}/toggle-status`; `level`: entry/mid/senior/lead/executive/Manager/Director |
| Groups | `/groups` | `GET /list`, `PATCH /{id}/toggle-status` |
| Channel Wise Employments | `/channel-wise-employments` | `GET /list`, `PATCH /{id}/toggle-status`, `GET/POST /{id}/head`, `GET /{id}/head/history` (permissions: `Channel Head Index/Assign`) |
| Employee Hierarchy | `/employees` | `GET /{id}/manager-history`, `POST /{id}/reassign-manager`, `GET /{id}/hierarchy-as-of` |

---

## 6. Access Management

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/permissions`, `/permissions/list` | `Permission Index` |
| POST/PUT/DELETE | `/permissions…` | `Permission Create/Update/Delete` |
| GET | `/roles`, `/roles/list` | `Role Index` |
| POST/PUT/DELETE | `/roles…` | `Role Create/Update/Delete` |
| GET | `/users` | `User Index` — filters: `search`, `role`, `branch_id`, `permission` |
| POST/PUT/DELETE | `/users…` | `User Create/Update/Delete` |
| PATCH | `/users/{id}/toggle-status` | `User Toggle Status` |

`GET /users?permission=Candidate Approve 2nd Interview` returns active holders of that permission (plus Super Admins) — use it to populate interviewer pickers.

Roles can be marked `is_protected` to prevent deletion. Assign the new list permissions to roles via `PUT /roles/{id}` (`permissions` array).

---

## 7. Notifications & Activity

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/notifications` | `Notification Index` (paginated, current user) |
| GET | `/notifications/unread-count` | `Notification Index` |
| PATCH | `/notifications/{id}/read` | `Notification Mark Read` |
| POST | `/notifications/mark-all-read` | `Notification Mark Read` |
| DELETE | `/notifications/{id}` | `Notification Mark Read` |
| GET | `/activity-logs`, `/activity-logs/{id}` | `Activity Log Index` |

Notification types: `candidate_submitted`, `candidate_interview_assigned`, `candidate_stage_status`.

---

## 8. Dashboard, Export & Import

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| GET | `/dashboard` | `Dashboard View` | Pipeline counts + statistics |
| GET | `/database/export` | `Database Export` | Schema export |
| GET | `/database/export-data` | `Database Export` | Full data export |
| GET | `/database/export-table/{table}` | `Database Export` | Single table |
| GET | `/database/export-all-tables` | `Database Export` | All tables |
| GET | `/import/tables` | `Import Index` | Importable tables |
| GET | `/import/catalog` | `Import Index` | Column catalog |
| GET | `/import/template/{table}` | `Import Index` | CSV template |
| POST | `/import/{table}` | `Bulk Import` | multipart CSV upload |

---

## 9. Seeding & Cache

```bash
php artisan db:seed --class=PermissionsSeeder   # idempotent; resets Spatie permission cache
```
After changing permissions outside the seeder: `php artisan permission:cache-reset`.

## 10. Tests

```bash
php artisan test
```
`tests/Feature/CandidateListPermissionTest.php` covers: 2nd-interview-list scoping, assigned-list scoping, 403 without permissions, unassigned-approver 403, assigned-approver success.
