# serp.cheap Rank Tracking — local demo stack

A one-command Docker environment to test the plugin against a **fake serp.cheap API**
(real HTTP calls, full SERP responses), with **WordPress + WooCommerce** and seeded
content.

## Run

```bash
cd packages/wordpress/dev
docker compose up -d --build
docker compose logs -f wpcli      # watch provisioning; it prints the URLs when ready
```

Then open:

- **Site:** http://localhost:8088
- **Admin:** http://localhost:8088/wp-admin — `admin` / `admin`
- **Mock API health:** http://localhost:8091/health

## What you get

- WordPress (latest) + WooCommerce, pretty permalinks.
- The plugin active in **real-HTTP mode** — it calls the mock API at `http://mock-api:8090`
  (driven by `SERPCHEAP_MOCK=false`, `SERPCHEAP_API_URL`, `SERPCHEAP_API_KEY` set in
  `WORDPRESS_CONFIG_EXTRA`). No in-plugin mock — these are genuine HTTP requests.
- Seeded posts, a page, WooCommerce products, a category and a product category, each
  with trackers and **14 days of backfilled history** (so sparklines/Δ7d are populated).
- The mock API (`mock-api/server.js`) returns the real `/v1/rank` shape: full fake
  organic SERP with your tracked URL placed at its rank, `matches`, related searches,
  people-also-ask, `X-API-Key` auth and a decrementing credit balance. Deterministic
  per url+keyword with day-to-day drift.

## Try it

- **serp.cheap → Rank Tracking** — central table of all trackers.
- Edit a post / product → **serp.cheap — Rank Tracking** box → add a keyword (live HTTP call).
- Posts/Products list → **Rank** column. Dashboard → summary widget.
- Force a refresh of all due trackers:
  ```bash
  docker compose exec -u 33 wordpress wp cron event run --due-now --allow-root || \
  docker compose run --rm wpcli wp cron event run --due-now
  ```
- Watch the API receive calls: `docker compose logs -f mock-api`

## Reset / stop

```bash
docker compose down          # stop, keep data
docker compose down -v       # stop and wipe (fresh install next time)
```
