# storage/

Runtime data created by Bird CMS at install/run time. Contents are
gitignored except this README.

Typical contents (created by `install-site.sh` and the engine):

- `cache/` — Markdown render cache, search corpus, computed metadata
- `logs/` — engine error logs (`error.log`, `audit.log`)
- `analytics/` — SQLite analytics database (`visits.sqlite`) when
  internal analytics mode is active
- `data/` — other engine state (e.g. `views.sqlite`, redirects)
- `checksums/` — page-level health audit snapshots used by
  `update.sh` auto-rollback (see `scripts/checksum-audit.php`)
- `backups/` — pre-update backups of the site directory

The directory is recreated empty on a fresh install. Nothing here is
checked into git — recreating the directory is enough; the engine
populates it on first run.
