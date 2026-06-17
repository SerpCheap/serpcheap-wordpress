# serp.cheap Rank Tracking — WordPress plugin

Track where your posts, pages, WooCommerce products, category archives and home
page rank on Google — right inside the WordPress admin. Powered by the
[serp.cheap](https://serp.cheap) Google Search API.

> Free plugin. You only pay for the serp.cheap API credits your rank checks consume.

## Features

- **Track any target** — posts, pages, WooCommerce products, category & product
  category archives, the site home, or any URL.
- **Per-target tracking** from the edit screen, with an inline mini-dashboard
  (current rank + sparkline).
- **Central dashboard** — a custom admin page aggregating every tracker, with
  metric cards, trend sparklines, a detail drawer with rank-history charts,
  search and filters.
- **Per-tracker scheduling** — hourly, 6h, 12h, daily, weekly, manual, or a
  custom interval. Global default plus per-tracker override.
- **Search depth** — choose how deep to scan (Top 10 → Top 100); default Top 10.
  Global default plus per-tracker override.
- **Credit-cost visibility** — live cost simulation before you create a tracker,
  per-tracker monthly projection, and an aggregate monthly estimate. See exactly
  what each decision costs.
- **Configurable alerts** — rank drops, leaving the top 10/3, dropping out of
  results, recovering into the top 10. In-app alert feed + optional email.
- **Rank column** on post/product list screens and a **dashboard widget** summary.
- **Theme-aware UI** — follows the admin color scheme you picked in your profile,
  and is fully responsive (desktop / tablet / phone).
- WordPress 6.0+ · PHP 7.4+ · WooCommerce 7.0+ (optional).

## Install

Copy the `serpcheap-rank-tracking/` directory into `wp-content/plugins/` and
activate it from **Plugins** in wp-admin. The built admin app under
`serpcheap-rank-tracking/build/` is committed, so no build step is required to run it.

## Local development

A one-command Docker stack (WordPress + WooCommerce + a fake serp.cheap API that
returns full SERP responses over real HTTP) lives in [`dev/`](dev/):

```bash
cd dev
docker compose up -d --build
docker compose logs -f wpcli   # prints the URLs when ready
```

- Site: http://localhost:8088 · Admin: `admin` / `admin`

See [`dev/README.md`](dev/README.md) for details.

## Building the admin app

The central dashboard is a React app built with `@wordpress/scripts`:

```bash
cd serpcheap-rank-tracking
npm install
npm run build      # or: npm run start  (watch mode)
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
