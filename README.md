# SerpCheap — Cheapest Keyword Rank Tracker (WordPress plugin)

Track where your posts, pages, WooCommerce products, category archives and home
page rank on Google — right inside the WordPress admin. Powered by the
[serp.cheap](https://serp.cheap) Search API.

> Free plugin. You only pay for the serp.cheap API credits your rank checks consume —
> and the UI shows the cost of every choice up front.

## Features

- **One-click connect** — "Connect to serp.cheap" runs a secure OAuth
  (Authorization-Code + PKCE) flow; a scoped, per-site API key is issued
  server-to-server and stored **encrypted**. It never passes through the browser.
- **Track anything** — posts, pages, WooCommerce products, category & product
  category archives, the site home, or any URL.
- **Per-target tracking** from the edit screen, with an inline rank + sparkline.
- **Custom dashboard** that follows your admin colour scheme (theme-aware) and is
  fully responsive: metric cards, trend sparklines, a detail drawer with
  rank-history charts, search and filters.
- **Per-tracker schedule** (hourly → weekly, or manual) and **search depth**
  (Top 10 → Top 100) — with global defaults and per-tracker overrides.
- **Credit-cost visibility** — live estimate before you create a tracker, a
  per-tracker monthly projection, and an account-wide monthly total.
- **Configurable alerts** — rank drops, leaving the top 10/3, dropping out,
  recovering — with an in-app feed and optional email.
- **Rank column** on post/product lists and a **Dashboard widget** summary.
- WordPress 6.0+ · PHP 7.4+ · WooCommerce 7.0+ (optional).

## Install

Install via **Plugins → Add New → Upload** with a zip of
`serpcheap-cheapest-keyword-rank-tracker/`, or copy that directory into
`wp-content/plugins/`. The built admin app under
`serpcheap-cheapest-keyword-rank-tracker/build/` is committed, so no build step is
required to run it.

Then open **serp.cheap → Settings → Connect to serp.cheap**.

## Development

```bash
cd serpcheap-cheapest-keyword-rank-tracker
composer install      # PHP dev deps (PHPUnit)
composer test         # unit tests (cost model, scheduling, encrypted key store)

npm install
npm run build         # build the React admin app (or: npm run start)
```

A one-command Docker stack (WordPress + WooCommerce + a fake serp.cheap API) lives
in [`dev/`](dev/) for local development:

```bash
cd dev && docker compose up -d --build
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
