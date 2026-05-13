# Releasing Bird CMS

This is the maintainer-facing checklist for cutting a release. Contributors
shouldn't need to read this — see `CONTRIBUTING.md` instead.

## Versioning

[Semver](https://semver.org/) with a pre-release suffix until 2.0.0 stable:

- `2.0.0-alpha.<N>` while we're still rewriting things between alphas (current).
- `2.0.0-beta.<N>` once Phase R1 (full L1 docs + composer migration) ships.
- `2.0.0-rc.<N>` once Phase R2 (perf + Live Benchmark Gate green) passes.
- `2.0.0` once Phase R3 (L2 partial) ships.

## Pre-flight

Before cutting any release:

- [ ] `make lint` is clean.
- [ ] `make smoke SITE_URL=...` passes against a live test site.
- [ ] `CHANGELOG.md` has a section for the new version with all notable
      changes since the previous tag, written in `keepachangelog.com`
      style.
- [ ] `VERSION` matches the new tag.
- [ ] `README.md` version line, if it has one, matches.
- [ ] No `*.pre-*-fix-*` files left in source from in-place patching.
- [ ] `.env.example` reflects all required env vars introduced this cycle.
- [ ] Phase gate (if applicable) has passed.

## Cut

```bash
# 1. Bump VERSION
echo "2.0.0-alpha.13" > VERSION

# 2. Update CHANGELOG.md - add the dated section at the top

# 3. Build the release archive (writes to releases/)
make release
# or: ./scripts/build-release.sh

# 4. Stage everything
git add VERSION CHANGELOG.md releases/bird-cms-2.0.0-alpha.13.tar.gz \
        releases/bird-cms-2.0.0-alpha.13.tar.gz.sha256 \
        releases/latest.txt

git commit -m "Release 2.0.0-alpha.13"

# 5. Tag
git tag -a v2.0.0-alpha.13 -m "Bird CMS 2.0.0-alpha.13"

# 6. Push
git push origin main --tags
```

## Verify cold clone

After every release, verify a fresh clone installs successfully:

```bash
cd /tmp
rm -rf bird-cms-clone
git clone https://gitlab.com/codimcc/bird-cms.git bird-cms-clone
cd bird-cms-clone
./scripts/install-site.sh /tmp/bird-clone-test test.local
# Expect: site directory created at /tmp/bird-clone-test, engine symlink set,
# .env present with default-deny ADMIN_ALLOWED_IPS.

php -S localhost:8888 -t /tmp/bird-clone-test/public &
curl -s http://localhost:8888/ | head -5
# Expect: a 200 response (or themed 404 page since no content yet - both fine).
kill %1
```

If the cold clone fails, the release is broken. Fix and re-tag.

## Post-release

- [ ] GitLab Release entry written (paste the CHANGELOG section). On GitLab:
      Project → Deploy → Releases → New release.
- [ ] Re-run the audit on a freshly-installed site to confirm scoring
      didn't regress (`php audit/scripts/full-audit.php https://test.local
      /tmp/bird-clone-test --save`).
- [ ] If the release closes any tracked issues, link them in the GitLab
      Release notes.
- [ ] If this is a security release, post a `SECURITY.md`-anchored
      advisory.

## Hotfix flow

For a security patch, the steps shrink:

1. Branch from the latest tag: `git checkout -b hotfix/<short-name> v2.0.0-alpha.12`.
2. Apply the minimal fix; resist scope creep.
3. Bump VERSION to `2.0.0-alpha.12-hotfix.1` (or roll the alpha forward).
4. Run pre-flight checks (lint + smoke).
5. Tag and push.
6. Cherry-pick the fix into `main` if the bug exists there too.

## Yanking a release

If a release ships a regression bad enough to recall:

1. Open the GitLab Release entry, mark it as a draft or remove the asset.
2. Update `releases/latest.txt` in `main` to point at the previous good
   version.
3. Push a new patch release with the fix as soon as possible.
4. Document the yank in `CHANGELOG.md` under the affected version.
