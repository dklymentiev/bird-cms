# Release process

Bird CMS uses **trunk-based** development with **versioned engine deploys** and
**atomic symlink flips** per site. This document covers shipping a release,
deploying it to multiple sites with canary safety, and rolling back if
something breaks.

## TL;DR — happy path (hotfix)

```bash
# 1. On the dev trunk (/server/scripts/bird-cms):
vim public/admin/index.php                       # make the fix
vim CHANGELOG.md                                 # add ## [3.1.6] — YYYY-MM-DD block

bash scripts/release.sh 3.1.6 --type hotfix
# -> bumps VERSION, commits, tags v3.1.6, builds archive, pushes GitLab.

# 2. Deploy with canary safety:
bash scripts/deploy-all.sh 3.1.6
# -> updates BIRD_CANARY first, smoke-tests, waits 30s, then bulk-updates
#    the rest and smoke-tests all of them. Restarts docker compose per
#    site so PHP-OPcache picks up the new engine.

# 3. Mirror to public GitHub (manual until automated):
cd /server/opensource/bird-cms
(cd /server/scripts/bird-cms && git archive HEAD) | tar xf - -C .
rm -rf .gitlab .gitlab-ci.yml             # mandatory — see project memory
git add -A && git commit -m "release: v3.1.6 — <summary>"
git tag -a v3.1.6 -m "v3.1.6 — <summary>"
git push origin main && git push origin v3.1.6
```

If anything breaks after step 2, **rollback in one command**:

```bash
bash scripts/rollback-all.sh 3.1.5
```

## Versioning

Bird CMS follows [SemVer](https://semver.org/):

| Bump  | When                                                                       | Example                |
|-------|----------------------------------------------------------------------------|------------------------|
| patch | bug fix or security hotfix, **no API change**                              | `3.1.4` → `3.1.5`      |
| minor | new feature, additive, **backward compatible**                             | `3.1.5` → `3.2.0`      |
| major | breaking change — schema, route, config, theme contract                    | `3.x` → `4.0.0`        |

A "hotfix" is just a patch release; there is no separate hotfix branch in this
project. Trunk-based works because the main branch is always shippable.

## Environment

The deploy and rollback scripts read two env vars:

```bash
export BIRD_SITES="/server/sites/topic-wise.com \
                   /server/sites/cleaninggta.com \
                   /server/sites/klymentiev.com \
                   /server/sites/klim.expert \
                   /server/sites/husky-cleaning.biz \
                   /server/sites/bird-cms.com"
export BIRD_CANARY="/server/sites/husky-cleaning.biz"
```

Set these once in `~/.bashrc` (or `/etc/environment` if you want them
available to cron jobs). `BIRD_CANARY` is the site that gets the new release
first; it should be the least-trafficked or test-only site you operate. If
you don't set it, `--skip-canary` is implied.

## Pre-release checks (do them before `release.sh`)

`release.sh` enforces these — they're listed here so you know what to fix
when it refuses to run:

1. **Branch = `main`.** No releases from feature branches.
2. **Working tree clean.** Commit or stash everything first.
3. **In sync with `origin/main`.** Run `git pull` if not.
4. **Tag doesn't already exist.** Don't reuse version numbers, ever.
5. **CHANGELOG.md has an entry for the new version.** Format:
   `## [3.1.6] - 2026-05-13` (no `v` prefix in the brackets).

## What `release.sh` does

```text
release.sh 3.1.6 --type hotfix
  ├─ verify all pre-flight checks (see above)
  ├─ write 3.1.6 to VERSION
  ├─ git add VERSION CHANGELOG.md
  ├─ git commit -m "release: v3.1.6"
  ├─ git tag -a v3.1.6 -m "v3.1.6 (hotfix)"
  ├─ bash scripts/build-release.sh
  │     └─ produces releases/bird-cms-3.1.6.tar.gz + .sha256
  └─ git push origin main && git push origin v3.1.6
```

It does **not** deploy anywhere. Deploy is a separate step on purpose, so
you can inspect the build before pushing to production.

## What `deploy-all.sh` does

```text
deploy-all.sh 3.1.6
  ├─ verify releases/bird-cms-3.1.6.tar.gz exists
  ├─ CANARY (if BIRD_CANARY set, unless --skip-canary):
  │   ├─ update.sh on canary site (extract + symlink flip)
  │   ├─ docker compose restart (flushes PHP-OPcache)
  │   ├─ curl smoke test (HTTP 200 on /)
  │   └─ ABORT if smoke failed; pause 30s if OK
  ├─ BULK (remaining sites):
  │   └─ for each site: update.sh + docker compose restart
  └─ FINAL smoke test on every site, exit 1 if any failed
```

The 30-second pause after canary is so you can `Ctrl-C` if you notice
something off in logs before the bulk wave starts.

`--skip-canary` skips the canary step (used when re-deploying the same
version to all sites, e.g. after an OPcache issue).

`--no-restart` skips the `docker compose restart` — useful when you know
sites have `PHP_OPCACHE_VALIDATE_TIMESTAMPS=1` and PHP will pick up the new
files within revalidate-frequency seconds. Default is to restart because
not all sites have that env set.

## What `rollback-all.sh` does

```text
rollback-all.sh 3.1.5
  └─ for each site in BIRD_SITES:
      ├─ skip if versions/3.1.5 not on disk (warn)
      ├─ rollback.sh <site> 3.1.5  (flip engine symlink)
      ├─ docker compose restart
      └─ smoke test https://<APP_DOMAIN>/
```

Rollback is just a symlink flip — instant, and any version that's still on
disk under `versions/` is a valid rollback target. We keep at minimum the
last two versions on each site for this reason.

## CHANGELOG.md hygiene

Format follows [Keep a Changelog](https://keepachangelog.com/):

```markdown
## [3.1.6] - 2026-05-13

One-line summary of the release.

### Added
- New things.

### Changed
- Modified behaviour.

### Fixed
- Bugs.

### Removed
- Deletions.

### Security
- Security-relevant fixes (don't omit even if you also wrote a Fixed entry).
```

Update CHANGELOG.md **before** running `release.sh` — it refuses to commit
without an entry for the new version. This keeps the changelog complete
without retroactive archaeology.

## Mirroring to GitHub (manual)

The public mirror at `/server/opensource/bird-cms/` (origin
`dklymentiev/bird-cms` on GitHub) is **not** automated yet. After each
release on GitLab, run:

```bash
cd /server/opensource/bird-cms
(cd /server/scripts/bird-cms && git archive HEAD) | tar xf - -C .
rm -rf .gitlab .gitlab-ci.yml             # MANDATORY — see note below
git add -A
git commit -m "release: v3.1.6 — <one-line summary>"
git tag -a v3.1.6 -m "v3.1.6 — <one-line summary>"
git push origin main
git push origin v3.1.6
```

**Why the `rm -rf` step is mandatory:** the dev trunk tracks `.gitlab/`
and `.gitlab-ci.yml`; every `git archive HEAD` re-introduces them in the
public mirror. Forgetting the cleanup ships GitLab CI metadata to GitHub
and forces a follow-up commit (the regression at `c7efe0d` is what
prompted this note).

## Post-release checklist

After a non-trivial release, take 5 minutes to:

- [ ] Open the affected URL in a browser (don't just `curl` — JS-side
      issues only show up rendered).
- [ ] Skim site error log: `docker compose logs --tail=50 -f web` on
      one site for a couple minutes.
- [ ] Verify analytics still writing:
      `stat -c %y /server/sites/<site>/storage/analytics/visits.db`
      — modification time should be recent.
- [ ] If schema-related: check `storage/data/edits.sqlite` is intact
      and writable.

## Rollback decision tree

- **Smoke test failed during deploy:** `deploy-all.sh` already exits
  non-zero; run `rollback-all.sh <prev>` immediately.
- **Found a bug minutes after release:** roll back, fix on trunk, ship
  a new patch release. Don't push more commits on top of a broken
  release.
- **Found a bug hours/days later:** judgement call. If reversible by
  a config change, do that. Otherwise fix-forward with a patch release.
- **Database / data corruption:** rollback engine + restore the most
  recent `/backup/backup_*.tar.gz` snapshot. The cron-driven daily
  backup at 03:30 includes content + .env + storage (sqlite DBs).

## What's NOT in this process (yet)

- Automated CI gate (`phpunit` + lint on every push). Roadmap.
- Staging environment that mirrors prod config. Today, the canary site
  doubles as staging.
- GPG-signed tags. Cheap to add (~30 min); see project memory for
  details.
- Automated GitHub mirror sync. Manual until we add a post-receive
  hook on GitLab or a CI job.
- Release announcement (GitHub Release page with notes). Skip for now;
  add when there are external users.
