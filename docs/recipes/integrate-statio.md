# Integrate Statio (5-minute setup)

Statio is the canonical analytics + lead-inbox for Bird CMS. It is
self-hostable (MIT, https://github.com/klymentiev/statio) and also
runs as a hosted service at https://statio.click.

This guide assumes you have a Statio instance somewhere and a Bird CMS
site you want to wire up.

## What you get

After integration:
- Form submissions on the Bird CMS site land in Statio's `/dashboard/leads`.
- Page views, sources, attribution land in Statio's analytics dashboards.
- One Statio instance handles N Bird CMS sites in a single dashboard.
- Bird CMS itself stays out of the analytics business -- no per-site
  CMS dashboards to babysit.

## Step 1 -- Provision a site in Statio

In your Statio dashboard:

1. Go to **Sites** -> **Add site**.
2. Enter the public URL of your Bird CMS site (e.g. `https://example.com`).
3. Statio prints three values you'll need:
   - `site_guid` -- looks like `st_AbCdEf123...`
   - `api_secret` -- 64-hex bearer token
   - Pixel snippet -- a `<script>` tag

## Step 2 -- Wire `.env` on the Bird CMS site

Edit `<site>/.env`:

```
STATIO_LEADS_ENDPOINT=https://your-statio-host/api/leads
STATIO_API_SECRET=<api_secret from step 1>
STATIO_SITE_GUID=<site_guid from step 1>
```

Restart the container if you're on Docker: `docker compose restart`.

`/api/lead` will start forwarding to Statio on the next request. With
the env vars unset it returns 503 with a hint.

## Step 3 -- Add the pixel to your theme

Open `themes/<your-theme>/layout.php` (or wherever your `<head>`
template lives) and paste the pixel snippet from Statio just before
`</head>`. It looks like:

```html
<script async src="https://your-statio-host/pixel.js?s=st_AbCdEf123" defer></script>
```

This is the only tracking script Bird CMS expects. Bird CMS does not
inject one on its own.

## Step 4 -- Verify

1. Open your Bird CMS site in a browser.
2. In Statio, go to **Recent visits** -- your visit should appear within
   ~10 seconds.
3. Submit a test lead via your contact form. Check **Leads inbox** in
   Statio -- the submission lands there with the visitor's session
   attribution attached (UTM, referrer, landing page).

## Troubleshooting

**`/api/lead` returns 503 `tracking_not_configured`.**
`STATIO_LEADS_ENDPOINT` or `STATIO_API_SECRET` is empty in `.env`.
Re-check spelling and restart the container.

**`/api/lead` returns 502 `tracking_unreachable`.**
Bird CMS reached out to Statio but got no response. Check that your
Statio host is up and reachable from the Bird CMS container's egress.
If Statio is on the same Docker host, ensure both are on the same
`docker network`.

**Leads land but pixel data doesn't.**
Pixel script tag is missing or blocked. View source on a page, search
for `pixel.js`. Check browser DevTools Network tab for the request --
ad blockers occasionally hit it; the snippet from Statio's site
provisioning step uses a path that bypasses common rules.

**Statio inbox shows visitors with no `landing_page` / `utm_*` data.**
The pixel hasn't loaded on the page where the form was submitted.
Make sure the pixel `<script>` is in your `<head>`, not just on the
homepage.

## Self-host vs cloud

`statio.click` is fine for evaluation but most production users
self-host on the same VPS/cluster as their Bird CMS sites:

```
git clone https://github.com/klymentiev/statio.git
cd statio
cp .env.example .env
docker compose up -d
```

Statio's setup is documented in its own README. The Bird CMS side of
the integration is identical regardless of where Statio lives.
