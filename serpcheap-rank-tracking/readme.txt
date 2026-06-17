=== serp.cheap Rank Tracking ===
Contributors: serpcheap
Tags: seo, rank tracking, google, serp, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track where your posts, pages, products, categories and home page rank on Google — right from the WordPress admin. Powered by the serp.cheap Search API.

== Description ==

Add keywords to any post, page, WooCommerce product, category, or your home page and see exactly where they rank on Google over time — without leaving WordPress.

A custom, theme-aware dashboard (it follows your admin colour scheme) shows every keyword with its current position, 7-day change and a trend chart. You connect your serp.cheap account once with a single click; a secure per-site key is issued to your site and rank checks run automatically.

**Tracking**

* Per-post / per-product / per-category keyword tracking, right from the edit screen, with an inline rank + sparkline.
* A central "Rank Tracking" page listing every keyword: position, 7-day change, trend, schedule and cost.
* A "Rank" column on your posts / pages / products lists, and a Dashboard widget summary.
* Track posts, pages, WooCommerce products, categories & product categories, your home page, or any URL.

**Control & cost**

* Per-tracker schedule: hourly, every 6h / 12h, daily, weekly, manual — plus a global default.
* Per-tracker search depth (Top 10 → Top 100) so you only pay for the depth you need.
* Live credit-cost estimate before you create a tracker, a per-tracker monthly projection, and an account-wide monthly total — so there are never billing surprises.

**Alerts**

* Configurable alerts for rank drops, leaving the top 10 / top 3, dropping out of results, or recovering — with an in-app feed and optional email.

**Connect**

* One-click "Connect to serp.cheap" (OAuth Authorization-Code + PKCE). A scoped, per-site API key is issued server-to-server and stored encrypted — it never passes through the browser. Disconnect any time to revoke it.

== External service ==

This plugin connects to the serp.cheap Search API (https://serp.cheap) to fetch Google rankings. When a keyword is checked, the target URL, keyword and country are sent to https://api.serp.cheap and counted against your serp.cheap account's credits. Connecting opens https://app.serp.cheap to authorize your site. See the Terms (https://serp.cheap/terms) and Privacy Policy (https://serp.cheap/privacy).

== Installation ==

1. Install via Plugins → Add New → Upload, or upload the `serpcheap-rank-tracking` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open **serp.cheap → Settings** and click **Connect to serp.cheap** — sign in (or create a free account) and authorize your site.
4. Edit any post / page / product, add a keyword in the "serp.cheap — Rank Tracking" box, and the rank appears. Manage everything from **serp.cheap → Rank Tracking**.

== Frequently Asked Questions ==

= Is the plugin free? =
Yes. You only pay for the serp.cheap credits your rank checks consume — pay-as-you-go, no monthly minimum. The plugin shows the cost of every choice up front.

= What can I track? =
Posts, pages, WooCommerce products, categories (including product categories), your home page, and any custom URL.

= Where is my API key stored? =
The per-site key is issued over a secure back-channel (it never appears in the browser) and stored encrypted in your database, keyed off your wp-config salts. Disconnecting revokes it on the server.

= How often are ranks checked? =
You choose per tracker — hourly through weekly, or manual. A global default applies to new trackers.

= Does it work with WooCommerce? =
Yes — products and product categories are supported automatically when WooCommerce is active.

== Screenshots ==

1. Central rank-tracking dashboard — every keyword with position, 7-day change, trend and per-tracker schedule.
2. Inline tracker on the post / product edit screen, with rank and sparkline.
3. Keyword detail with rank-history chart and search-depth control.
4. Credit-cost estimate shown before you create a tracker.
5. Configurable rank alerts.
6. Dashboard widget summary.

== Changelog ==

= 1.0.0 =
* First public release.
* One-click secure connect to serp.cheap (OAuth Authorization-Code + PKCE; encrypted per-site key).
* Custom, theme-aware, responsive admin dashboard; per-target metabox; Rank column; Dashboard widget.
* Per-tracker schedule and search depth, with global defaults.
* Credit-cost visibility: pre-create estimate, per-tracker and account-wide monthly projection.
* Configurable rank alerts with in-app feed and optional email.
* WooCommerce products & product categories supported.
