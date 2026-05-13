# API call examples

Working examples for the public APIs Bird CMS exposes. All endpoints expect
JSON request bodies, return JSON responses, and reject cross-origin requests
that don't match the configured `site_url`.

The shell snippets below assume the site is configured as
`SITE_URL=https://example.com`. Adapt to your site URL.

## /api/lead.php - Lead capture

Persists a lead to `storage/leads/` and sends an SMTP notification (over
TLS - STARTTLS or implicit on port 465).

### POST request

```bash
curl -X POST https://example.com/api/lead.php \
  -H "Origin: https://example.com" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.org",
    "phone": "+1-555-0100",
    "message": "Need a quote for cleaning service.",
    "service": "deep_cleaning",
    "source": "google_ads"
  }'
```

### Successful response (HTTP 200)

```json
{
  "success": true,
  "lead_id": "2026-04-27-abc123"
}
```

### Cross-origin rejection (HTTP 200 but with site origin echoed, not yours)

```bash
curl -X POST https://example.com/api/lead.php \
  -H "Origin: https://attacker.example" \
  -H "Content-Type: application/json" \
  -d '{...}' \
  -i
# HTTP/2 200
# Access-Control-Allow-Origin: https://example.com    ← not 'https://attacker.example'
```

## /api/subscribe.php - Newsletter subscribe

Same origin-validation pattern. Persists subscriber to
`storage/subscribers/` (file-backed today; SQLite migration is on the
roadmap).

```bash
curl -X POST https://example.com/api/subscribe.php \
  -H "Origin: https://example.com" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "subscriber@example.org",
    "source": "blog_footer"
  }'
```

Response on success:

```json
{ "success": true }
```

## /api/track-event.php - Analytics event

Tracks a conversion event in the analytics SQLite DB
(`storage/analytics/visits.db`).

```bash
curl -X POST https://example.com/api/track-event.php \
  -H "Origin: https://example.com" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "phone_reveal",
    "page": "/services/deep-cleaning/",
    "meta": {
      "source": "cta_button",
      "variant": "control"
    }
  }'
```

Allowed event types (from `track-event.php` validator):

- `phone_reveal`, `phone_click`
- `cta_click`, `form_submit`, `form_error`
- `outbound_click`, `download`
- `video_play`, `video_complete`
- `search`, `share`

Response on success: `204 No Content`.

## /admin/login - Admin authentication

Unlike the public APIs, admin endpoints are subject to two gates:

1. `Auth::isIpAllowed()` - IP must be in `ADMIN_ALLOWED_IPS`.
   Unallowed IPs receive the site's themed 404 page.
2. Session cookie + bcrypt-verified password.

### Login from JS (admin frontend, same-origin)

```javascript
const csrfToken = document.querySelector('input[name="_csrf"]').value;

const res = await fetch('/admin/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    _csrf: csrfToken,
    username: 'admin',
    password: 'your-password-here',
  }),
});

if (res.ok && res.url.endsWith('/admin/')) {
  // logged in, session cookie set
}
```

### Login from cURL (less common - use the panel)

```bash
# 1. Get CSRF token from the login page
curl -s -c cookies.txt https://example.com/admin/login \
  | grep -oE 'name="_csrf" value="[^"]+"' \
  | sed -E 's/.*value="([^"]+)".*/\1/' > csrf.txt

# 2. Submit credentials
curl -b cookies.txt -c cookies.txt \
  -X POST https://example.com/admin/login \
  -d "_csrf=$(cat csrf.txt)" \
  -d "username=admin" \
  -d "password=YOUR_PASSWORD"

# Subsequent admin requests use cookies.txt for session
```

## Common failure modes

| Symptom | Likely cause |
|---|---|
| `404 Not Found` on `/api/lead.php` from a non-admin IP | Working as designed for `/admin/*`. For `/api/*`, this means the endpoint genuinely isn't there - check the engine symlink. |
| `405 Method Not Allowed` | You sent a GET or PUT. All public APIs accept only POST + OPTIONS preflight. |
| `Access-Control-Allow-Origin: https://example.com` when you sent a different `Origin` header | Working as designed: the response only echoes back the configured site origin. Browsers will reject cross-origin reads. |
| Lead form submits successfully but no email arrives | Check `error_log` for `SMTP server ... did not advertise STARTTLS`. The TLS-mandatory rule may be rejecting your provider - switch to a STARTTLS-capable port (587 / 465). |
