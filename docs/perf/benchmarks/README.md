# Bird CMS benchmarks

Three scripts. Run any of them standalone.

## render.sh

Measures HTTP render latency against any URL. Uses `wrk` if installed
(preferred — gives proper P50/P95/P99); falls back to `ab`.

```
./docs/perf/benchmarks/render.sh                                    # localhost defaults
./docs/perf/benchmarks/render.sh https://bird-cms.com/ 30s 10       # remote, 30s, 10 conns
```

What it measures: full HTTP request → response time, including
network. Pure engine time is "this minus your network RTT to the
host."

## deploy.sh

Times the two deploy operations: fresh install (`install-site.sh`)
and version upgrade (`update.sh` symlink swap).

```
./docs/perf/benchmarks/deploy.sh
```

Requires two release tarballs in `releases/`. Run `make release`
twice across two version tags to populate.

## cache.sh

Measures the impact of `CONTENT_CACHE=true` on a 500-article site.
Seeds bundle articles under `tests/fixtures/bench/articles/perf/` on
first run, then times `/` and `/admin/articles` with and without the
cache enabled.

```
./docs/perf/benchmarks/cache.sh                                     # localhost, 10 samples
./docs/perf/benchmarks/cache.sh http://localhost:8080/ 25           # 25 samples
```

The script handles seeding, cache wiping, and timing. The
`CONTENT_CACHE` env-var flip on the server is the operator's job:
between the "without cache" and "with cache" runs, restart the PHP
process with `CONTENT_CACHE=true` in the environment.

### What it measures

| Scenario                  | What it captures                                 |
|---------------------------|--------------------------------------------------|
| home cold (no cache)      | every request reparses 500 YAML + renders 500 MD |
| home cold (cache, first)  | one regen, writes `storage/cache/articles-index.php` |
| home warm (cache, next N) | opcache-served include, no reparsing             |
| `/admin/articles`         | URL Inventory hits `all()` 5+ times per request  |

### Reference numbers

Not yet captured on a representative host. To populate:

1. Stand up a Bird CMS instance with this branch
   (`feat/repository-caching`).
2. Point `content/articles/` at `tests/fixtures/bench/articles/`
   (or copy the seeded `perf/` category in).
3. Run `cache.sh` once with `CONTENT_CACHE` unset, once with
   `CONTENT_CACHE=true`, and record the avg times.

Target on a 500-article site: 50%+ reduction in cold-cache wall time
for `/` and the same or better for `/admin/articles` (which calls
`Repository::all()` repeatedly per request, so the cache hit ratio is
higher than for the home page).

