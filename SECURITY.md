# Security Policy

## Reporting a Vulnerability

If you believe you've found a security issue in Bird CMS, please report it
**privately** rather than opening a public issue.

Email: **security@klymentiev.com**

Please include:
- A description of the issue and the impact
- Steps to reproduce, or a proof-of-concept
- The Bird CMS version (`cat VERSION`) and any relevant configuration details
- Your name and a link to your work, if you'd like to be credited

You can expect:
- An acknowledgement within **3 business days**
- A first assessment within **7 business days**
- A coordinated disclosure timeline (typically 30–90 days, longer for complex
  issues)

We don't operate a paid bug bounty program.

## Supported Versions

Only the **latest 2.0 release** receives security patches. Older versions
(including the 1.x line) are unsupported — please upgrade.

| Version                | Supported |
| ---------------------- | --------- |
| 3.x (latest)           | Yes       |
| 2.0.0-beta.x           | No        |
| 2.0.0-alpha.x          | No        |
| 1.x                    | No        |

## In Scope

- Code in `app/`, `bootstrap.php`, `public/`, `themes/`, `scripts/`
- Admin panel authentication and authorization
- Content rendering and templating (XSS, SSRF, RCE)
- API endpoints under `public/api/`
- Update / rollback machinery (`scripts/update.sh`, `scripts/check-update.sh`,
  `docker/entrypoint.sh`)
- Default configuration files (`.env.example`, `config/`)

## Out of Scope

- Vulnerabilities that require physical access or local OS-level access
- Issues caused by misconfigurations diverging from the documented defaults
  (e.g. setting `ADMIN_ALLOWED_IPS=` empty in production, running with
  `DEBUG=true` exposed)
- Third-party dependencies pinned by your deployment that we don't control
- Findings against unsupported versions (see table above)
- Self-inflicted issues (e.g. logging into your own admin and uploading
  malicious content as an authenticated admin)

## Security Defaults

Bird CMS ships **default-deny** for the admin panel:

- `ADMIN_ALLOWED_IPS=127.0.0.1` — only loopback can reach `/admin` until you
  explicitly add your IP/CIDR
- `TRUSTED_PROXIES=127.0.0.1,::1,172.16.0.0/12` — proxy headers are honored
  only when the TCP connection comes from a known proxy
- `DEBUG=false` — PHP error output is suppressed in HTTP responses
- Boot fails loud if `APP_KEY` is missing or set to a known-insecure default
- Auto-updates are off by default; enable via `ENABLE_AUTO_UPDATE=true`
- SVG uploads are not accepted (XSS via embedded `<script>`)
- SMTP transmits credentials only over a TLS-encrypted channel (STARTTLS or
  port 465 implicit TLS); plain auth is refused

If you find a default that you believe should be tightened further, that's a
valid report.
