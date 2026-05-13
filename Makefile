.PHONY: help lint test smoke audit install release clean

help:
	@echo "Bird CMS — common targets:"
	@echo ""
	@echo "  make lint                     PHP syntax check across app/, public/, scripts/"
	@echo "  make test                     Run PHPUnit suite (vendor/bin/phpunit)"
	@echo "  make smoke SITE_URL=...       HTTP smoke test (default: http://localhost)"
	@echo "  make audit SITE_URL=... SITE_DIR=... [--save]"
	@echo "                                Full SEO/security audit of a deployed site"
	@echo "  make install TARGET=... DOMAIN=..."
	@echo "                                Install a fresh site from the latest release"
	@echo "  make release                  Build the next release archive (uses VERSION)"
	@echo "  make clean                    Remove storage caches in working dir"

lint:
	@echo "→ Linting PHP files..."
	@find app public scripts -name '*.php' -print0 \
		| xargs -0 -n1 -P4 php -l \
		| grep -v 'No syntax errors' || echo "OK — no syntax errors"

test:
	@if [ ! -x vendor/bin/phpunit ]; then \
		echo "vendor/bin/phpunit missing — run 'composer install' first"; \
		exit 1; \
	fi
	@vendor/bin/phpunit

smoke:
	@bash tests/smoke-test.sh $${SITE_URL:-http://localhost}

audit:
	@if [ -z "$(SITE_URL)" ]; then echo "Set SITE_URL=https://example.com"; exit 1; fi
	@php audit/scripts/full-audit.php $(SITE_URL) $${SITE_DIR:-.} $(EXTRA)

install:
	@if [ -z "$(TARGET)" ] || [ -z "$(DOMAIN)" ]; then \
		echo "Usage: make install TARGET=/var/www/example.com DOMAIN=example.com"; \
		exit 1; \
	fi
	@bash scripts/install-site.sh $(TARGET) $(DOMAIN)

release:
	@bash scripts/build-release.sh

clean:
	@rm -rf storage/cache/* storage/logs/*.log
	@echo "Cleaned storage/cache/, storage/logs/"
