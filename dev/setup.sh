#!/bin/sh
# One-shot provisioning: install WP, WooCommerce, the plugin, and seed demo content.
set -e
cd /var/www/html

echo "[setup] waiting for WordPress core + wp-config.php..."
i=0
while [ ! -f wp-config.php ] || ! wp core version >/dev/null 2>&1; do
	i=$((i + 1))
	if [ "$i" -gt 120 ]; then echo "[setup] timeout waiting for core"; exit 1; fi
	sleep 3
done

echo "[setup] waiting for database..."
while ! wp db check >/dev/null 2>&1; do sleep 3; done

if ! wp core is-installed >/dev/null 2>&1; then
	echo "[setup] installing WordPress..."
	wp core install \
		--url="http://localhost:8088" \
		--title="serp.cheap Rank Tracking — Demo" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@example.com \
		--skip-email
fi

wp rewrite structure '/%postname%/' >/dev/null 2>&1 || true
wp rewrite flush --hard >/dev/null 2>&1 || true

echo "[setup] WooCommerce..."
wp plugin is-installed woocommerce >/dev/null 2>&1 || wp plugin install woocommerce
wp plugin activate woocommerce >/dev/null 2>&1 || true
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1 || true

echo "[setup] activating serp.cheap Rank Tracking..."
wp plugin activate serpcheap-rank-tracking

echo "[setup] seeding content + trackers (real HTTP calls to the mock API)..."
wp eval-file /seed.php || echo "[setup] seed step reported an issue (continuing)"

echo ""
echo "==================================================================="
echo "  serp.cheap Rank Tracking demo is READY"
echo "    Site:     http://localhost:8088"
echo "    Admin:    http://localhost:8088/wp-admin   (admin / admin)"
echo "    Mock API: http://localhost:8091/health"
echo "  Plugin runs in REAL-HTTP mode against the mock API (mock-api:8090)."
echo "==================================================================="
