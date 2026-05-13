# Bird CMS - API Reference

> Why this section matters: APIs are contracts. A change that breaks
> a contract without a major-version bump breaks every integrator
> downstream. This document is the contract; treat it as canonical.

## Endpoints overview

| Endpoint | Method | Auth | Body | Purpose |
|---|---|---|---|---|
| `/api/v1/health` | GET | none | — | Liveness probe `{ok, version}` |
| `/api/v1/content/<type>[/<cat>]/<slug>` | GET/POST/DELETE | Bearer | JSON | Content CRUD across 5 types |
| `/api/v1/url-inventory` | GET | Bearer (read) | — | All URLs with sitemap meta |
| `/api/v1/url-meta/<path>` | GET/PUT | Bearer | JSON | Per-URL overrides |
| `/api/v1/site-config` | GET/PUT | Bearer | JSON | Whitelist-only site config |
| `/api/v1/assets/upload` | POST | Bearer (write) | multipart | Upload to `uploads/` |
| `/api/v1/assets/<path>` | GET/DELETE | Bearer | — | Asset metadata / delete |
| `/api/lead.php` | POST | Origin check | JSON | Lead capture + SMTP notify |
| `/api/subscribe.php` | POST | Origin check | JSON | Newsletter subscribe |
| `/api/track-event.php` | POST | Origin check | JSON | Conversion event tracking |
| `/admin/login` | POST | CSRF + creds | form | Admin login |
| `/admin/logout` | POST | CSRF + session | form | Admin logout |
| `/admin/articles/...` | GET/POST | session + IP allow-list | varies | Article CRUD |
| `/admin/media/...` | GET/POST | session + IP allow-list | multipart | Media upload |
| `/admin/api-keys/...` | GET/POST | session + IP allow-list | form | Manage `/api/v1` keys (full mode) |
| `/admin/api/...` | GET/POST | session + IP allow-list | JSON | Admin internal APIs |

The three public APIs (`lead`, `subscribe`, `track-event`) share a common
preflight and CORS posture. Admin endpoints share a different posture. Both
groups documented below.

## Common: public APIs

### Origin policy

All public endpoints **only** echo the configured `site_url` in the
`Access-Control-Allow-Origin` response header. Wildcard `*` is never
emitted. Cross-origin POST from a non-matching origin still receives a
response (the server can't refuse before reading the request), but the
echoed origin will not match the caller, so the browser will reject the
read.

This means: any third-party JS embedded on `site_url` can call these
endpoints. Any third-party page that tries to POST cross-origin will see
a CORS rejection in the browser console.

### Preflight (OPTIONS)

```http
OPTIONS /api/lead.php HTTP/1.1
Origin: https://example.com
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Content-Type
```

Response:

```http
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: https://example.com
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

### Method handling

Only `POST` and `OPTIONS` are accepted. `GET`, `PUT`, `DELETE`, `PATCH`
return:

```http
HTTP/1.1 405 Method Not Allowed
Content-Type: application/json

{"success": false, "error": "Method not allowed"}
```

## POST /api/lead.php - Lead capture

### Request body

```json
{
  "name": "string (required, 1–200 chars)",
  "email": "string (required, RFC 5322 format)",
  "phone": "string (optional)",
  "message": "string (optional, max 5000 chars)",
  "service": "string (optional, free-form tag)",
  "source": "string (optional, e.g. 'google_ads', 'organic')",
  "page": "string (optional, the page URL the form was on)",
  "company": "string (optional)",
  "budget": "string (optional)",
  "service_type": "string (optional)",
  "bedrooms": "string|integer (optional, domain-specific)",
  "bathrooms": "string|integer (optional)",
  "frequency": "string (optional)",
  "date": "string (optional, ISO 8601)",
  "time": "string (optional, HH:MM)",
  "address": "string (optional)"
}
```

Server-side timestamp and client IP are added automatically.

### Successful response

```http
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: https://example.com

{"success": true, "lead_id": "2026-04-27-a1b2c3"}
```

### Error responses

```http
HTTP/1.1 400 Bad Request
{"success": false, "error": "Missing required field: email"}
```

```http
HTTP/1.1 405 Method Not Allowed
{"success": false, "error": "Method not allowed"}
```

### Side effects

- File `storage/leads/<lead_id>.json` written with the full payload + meta.
- SMTP notification sent to `LEAD_EMAIL` (env) or `contacts.leads`
  (config) - TLS-mandatory. If SMTP fails (no TLS, bad creds, network
  error), the lead is still saved to disk; the operator sees it in the
  admin lead inbox.

### Rate limiting

None at the application layer today. Use upstream protection (Cloudflare
rules, fail2ban) if needed. Per-endpoint rate limit is on the roadmap
(R3).

## POST /api/subscribe.php - Newsletter subscribe

### Request body

```json
{
  "email": "string (required, RFC 5322)",
  "source": "string (optional, e.g. 'blog_footer')",
  "name": "string (optional)"
}
```

### Successful response

```http
HTTP/1.1 201 Created
{"success": true, "message": "..."}
```

If the email is already on the list, the endpoint is idempotent:

```http
HTTP/1.1 200 OK
{"success": true, "already_subscribed": true, "message": "..."}
```

### Error responses

```http
HTTP/1.1 400 Bad Request
{"success": false, "error": "Email is required"}

HTTP/1.1 400 Bad Request
{"success": false, "error": "Invalid email address"}
```

### Rate limiting

Same email may not be re-submitted within `RATE_LIMIT_WINDOW` seconds
(default 60). Beyond the window, the endpoint stays idempotent.

## POST /api/track-event.php - Conversion event

### Request body

```json
{
  "event": "phone_reveal | phone_click | form_submit | form_start | cta_click | quote_request | email_click | whatsapp_click | newsletter_subscribe",
  "page": "string (required, URL path the event happened on)",
  "meta": {
    "...": "free-form key-value, max 4 KB serialized"
  }
}
```

### Successful response

```http
HTTP/1.1 200 OK
Access-Control-Allow-Origin: https://example.com
{"success": true, "event_id": 123, "session_id": "..."}
```

### Error responses

```http
HTTP/1.1 400 Bad Request
{"error": "Invalid event type"}
```

### Side effects

Row inserted into `storage/analytics/visits.db` (SQLite) `events` table.

## Admin endpoints

### Auth posture

Two gates run on every request to `/admin/*`:

1. **IP allow-list** - `Auth::isIpAllowed()` consults `ADMIN_ALLOWED_IPS`
   in `.env`. Unallowed IPs receive `HTTP 404` with the site's themed
   404 page. The admin's existence is not advertised.
2. **Session** - once IP is allowed, the controller checks
   `Auth::check()` for a valid session. Unauthenticated requests are
   redirected to `/admin/login`.

The IP detection priority (only when `REMOTE_ADDR` is in
`TRUSTED_PROXIES`):

1. `CF-Connecting-IP`
2. `X-Real-IP`
3. `REMOTE_ADDR` (always the fallback)

### POST /admin/login

```http
POST /admin/login HTTP/1.1
Cookie: bird_admin_session=...
Content-Type: application/x-www-form-urlencoded

_csrf=<256-bit hex>&username=admin&password=...
```

Successful login: HTTP 302 to `/admin/`, session cookie issued.
Failed login: HTTP 200 with login form re-rendered, error flash.

After 5 consecutive failures from the same IP within
`lockout_duration` (default 900s), further attempts return HTTP 200 with
"locked out" message regardless of correct credentials.

### POST /admin/logout

```http
POST /admin/logout HTTP/1.1
Cookie: bird_admin_session=...
Content-Type: application/x-www-form-urlencoded

_csrf=<csrf>
```

HTTP 302 to `/admin/login`, session destroyed.

### Admin internal APIs

Bird CMS ships several admin-only JSON endpoints under `/admin/api/`
(article CRUD, media listing, analytics queries). These are intentionally
not documented as a stable contract - they're internal to the admin
panel, are versioned with the engine, and may change between alpha
releases without notice. If you need a stable contract for a third-party
integration, file an issue.

## Public REST API v1 (`/api/v1`)

> Stability: **stable from `3.2.0` onwards**. Breaking changes require a
> major-version bump.

Bird CMS exposes a public HTTP REST API mirroring the MCP tool
surface for non-AI integrations (mobile apps, third-party
publishers, headless frontends). It uses Bearer-token auth instead
of the session+IP-allowlist posture of the admin panel.

### Auth

Every request (except `/api/v1/health`) requires an `Authorization`
header:

```http
Authorization: Bearer <key>
```

Keys are 64-character hex strings generated server-side via
`bin2hex(random_bytes(32))`. The **plaintext is shown exactly once**
in the admin UI at the moment of creation; only the SHA-256 hash is
persisted to `storage/api-keys.json`. Lost keys must be revoked and
replaced.

Failure modes collapse to `401` without revealing the cause:
- missing `Authorization` header
- malformed Bearer value
- unknown key
- revoked key

A scope mismatch (key valid but cannot perform the requested verb) is
`403` so the caller knows to rotate the key rather than retry.

### Scopes

| Scope  | Verbs               | Notes |
|--------|---------------------|-------|
| `read` | GET                 | Cannot mutate. Cannot upload assets. |
| `write`| GET + POST + PUT + DELETE | Full CRUD across content, URL meta, site config (whitelist), and assets. |

### Rate limit

60 requests per minute per key (sliding window). Counter is keyed on
the SHA-256 of the key, NOT the caller IP, so clients behind shared
CGNAT don't share a bucket. When the limit is exceeded:

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 47
X-RateLimit-Remaining: 0
Content-Type: application/json

{"error": {"code": "rate_limited", "message": "API key over the per-minute request budget. Retry after 47s."}}
```

Allowed responses also set `X-RateLimit-Remaining` so callers can
monitor budget proactively.

### Error format

All errors return JSON with a discriminator field:

```json
{
  "error": {
    "code":    "<machine-readable-code>",
    "message": "<human-readable-text>"
  }
}
```

Common codes:

| HTTP | `code`            | Meaning |
|------|-------------------|---------|
| 400  | `invalid_body`    | Request body could not be parsed or is missing required keys. |
| 400  | `invalid_slug`    | Slug must match `[a-z0-9-]+`. |
| 400  | `invalid_category`| Category must match `[a-z0-9-]+`. |
| 400  | `invalid_path`    | Path traversal / absolute path / Windows drive letter rejected. |
| 400  | `invalid_input`   | Field-level validation failure (timezone, URL, theme, language, etc.). |
| 401  | `unauthorized`    | Missing / invalid / unknown / revoked key. |
| 403  | `forbidden`       | Authenticated, but scope does not permit the verb. |
| 404  | `not_found`       | Item or endpoint does not exist. |
| 404  | `unsupported_type`| Content type segment is not registered. |
| 415  | `unsupported_type`| Upload MIME type is not on the allow-list. |
| 429  | `rate_limited`    | 60-req/min budget exhausted. |
| 500  | `internal_error`  | Unhandled exception; details are logged server-side. |
| 503  | `not_installed`   | Hit `/api/v1` before the install wizard completed. |

### Endpoints

#### Health

```http
GET /api/v1/health
```

Returns `{ok: true, version: "<VERSION-file-contents>"}`. **Not
authenticated** -- safe for load balancers and uptime monitors.

#### Content CRUD

The 5 content types are: `articles`, `pages`, `services`, `areas`,
`projects`. Articles and services require a `<category>` segment;
the other types take just a `<slug>`.

```http
GET    /api/v1/content/<type>                       # list (published only)
GET    /api/v1/content/<type>/<slug>                # read
GET    /api/v1/content/articles/<cat>/<slug>        # read article
GET    /api/v1/content/services/<cat>/<slug>        # read service
POST   /api/v1/content/<type>/<slug>                # create or update
POST   /api/v1/content/articles/<cat>/<slug>        # create or update article
POST   /api/v1/content/services/<cat>/<slug>        # create or update service
DELETE /api/v1/content/<type>/<slug>                # idempotent delete
DELETE /api/v1/content/articles/<cat>/<slug>        # idempotent delete article
DELETE /api/v1/content/services/<cat>/<slug>        # idempotent delete service
```

Write body:

```json
{
  "frontmatter": {
    "title":       "Launch Notes",
    "description": "First day in production",
    "date":        "2026-05-10",
    "status":      "published",
    "tags":        ["launch"],
    "type":        "announcement",
    "primary":     "launch"
  },
  "body": "# Hello world\n\nMarkdown goes here."
}
```

The `frontmatter` shape matches what the MCP `write_article` tool
accepts -- the public API and the MCP server share the same
repository write path.

List responses strip drafts even for `read` scope; the API never
leaks unpublished work.

#### URL inventory

```http
GET /api/v1/url-inventory
```

Returns every URL on the site (homepage, every content-type item,
article category indexes) with per-URL sitemap metadata merged from
`storage/url-meta.json`. Mirrors the data shown on `/admin/pages`.

```http
GET /api/v1/url-meta/<path>
PUT /api/v1/url-meta/<path>
```

`<path>` is the URL path the override applies to (e.g. `/blog/launch-notes`).
PUT body is a partial merge of:

```json
{
  "in_sitemap": false,
  "noindex":    true,
  "priority":   "0.4",
  "changefreq": "monthly",
  "template":   "service-deluxe"
}
```

Validation:
- `priority` must match `/^(0(\.\d+)?|1(\.0+)?)$/`
- `changefreq` must be one of: `always`, `hourly`, `daily`,
  `weekly`, `monthly`, `yearly`, `never`
- `..` anywhere in `<path>` is rejected with `invalid_path` so the
  meta-key namespace can't be confused via path normalisation.

#### Site config

```http
GET /api/v1/site-config
PUT /api/v1/site-config
```

Whitelisted fields only:
- `site_name`
- `site_description`
- `site_url`
- `active_theme` (must name a real folder under `themes/`, never
  `admin` or `install`)
- `timezone` (must be a valid `\DateTimeZone::listIdentifiers()` entry)
- `language` (must match `/^[a-z]{2}(-[A-Z]{2})?$/`)

The PUT semantics are **merge**: unspecified fields keep their
current value. The same whitelist and validator that govern the
admin Settings > General tab govern this endpoint, so:

- `app_key`, `admin_password_hash`, `admin_allowed_ips`, and every
  other .env-resident secret are **never** reachable through this
  endpoint -- the whitelist drops anything outside the six fields
  above before validation runs.

#### Assets

```http
POST   /api/v1/assets/upload      # multipart/form-data
GET    /api/v1/assets/<path>      # metadata + download URL
DELETE /api/v1/assets/<path>      # idempotent delete
```

Upload body: `file` (binary) + `path` (string, relative). Files land
under `uploads/<path>` -- never `public/assets/` (which the admin
Media tab manages and is part of the versioned engine bundle).
`uploads/` is operator-owned and survives engine upgrades.

Constraints:
- Max size: **10 MiB**
- Allowed MIME types (sniffed from bytes, not Content-Type):
  `image/jpeg`, `image/png`, `image/gif`, `image/webp`,
  `image/svg+xml`, `application/pdf`
- Path traversal is rejected up-front: `..`, leading `/`, drive
  letters, UNC prefixes, NUL bytes, and reserved Windows filenames
  (`CON`, `PRN`, `AUX`, `NUL`, `COM1-9`, `LPT1-9`) all return 400.
- The resolved target is re-checked against `realpath()` of the
  uploads root, so a symlink inside `uploads/` cannot route outside.

`show()` returns metadata + `download_url`; the binary itself is
served by nginx at `/uploads/<path>` directly, not through PHP.

### Managing keys

Use the admin UI at `/admin/api-keys` (visible in the sidebar under
`ADMIN_MODE=full`). The routes themselves resolve in either mode so
an operator who knows the URL can manage keys on a minimal-mode site
too.

| Path                            | Method | Purpose |
|---------------------------------|--------|---------|
| `/admin/api-keys`               | GET    | List active + revoked keys |
| `/admin/api-keys/new`           | GET    | Form (label + scope)       |
| `/admin/api-keys/create`        | POST   | Generate + show plaintext  |
| `/admin/api-keys/{hash}/revoke` | POST   | Mark `revoked_at`          |

## Versioning + breaking changes

- Public API endpoints (`lead`, `subscribe`, `track-event`) are
  considered stable from `2.0.0` onwards. Breaking changes there require
  a major-version bump.
- Admin internal APIs are unstable and may break between any two
  releases.
- Schema.org output formats follow Google's structured-data validator
  evolution; if Google relaxes a requirement, we may relax our emission
  without considering it a breaking change.

## Error format

Public APIs return errors in one of two shapes:

```json
{"success": false, "error": "human-readable message"}
```

or

```json
{"error": "human-readable message"}
```

We're standardizing on the first shape (Phase R3 deliverable). Until
then, both are valid responses; integrators should handle either.
