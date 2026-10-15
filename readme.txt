=== OSM Badge Supplier ===
Contributors: leicestershirescouts
Tags: woocommerce, osm, scouts, badges
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Online Scout Manager (OSM) badge orders to WooCommerce: map stock_code to SKU and stage the cart.

== Description ==

For Scout shops that sell badges via Online Scout Manager’s Badge Supplier programme.

Flow:

1. A leader clicks Buy Badges in OSM; OSM opens your shop landing page with `?order_url={url-encoded relative path}`.
2. The plugin immediately POSTs to `{osm_host}{order_url}` with `client_id` in the form body only (never in the URL).
3. Each `badges[].stock_code` is mapped to a WooCommerce product SKU and the quantity is added to the cart.
4. The first landing page lists every unfulfillable item (null stock_code, missing SKU, not purchasable) before checkout.
5. The leader pays on your shop; OSM never takes payment.

Staged badges: each level must have its own stock_code/SKU. The plugin uses whatever stock_code OSM sends and never guesses from the badge name.

If your web host’s outbound IP is blocked by OSM, you can optionally route the order fetch through an HTTPS relay (for example a Cloudflare Worker). See the `osm-relay-worker/` companion in this repository.

== Installation ==

1. In WordPress: Plugins → Add New → Upload Plugin → choose `osm-badge-supplier.zip` → Install Now → Activate.
2. Ensure WooCommerce is installed and active.
3. Go to WooCommerce → Settings → OSM Badges (or WooCommerce → OSM Badge Supplier).
4. Set Client ID (password field). Do not commit this value to version control.
5. Confirm landing path slug (default `osm-order`) and OSM host (default `https://www.onlinescoutmanager.co.uk`, HTTPS, no trailing slash, use `www`).
6. Visit Settings → Permalinks and click Save once (flushes rewrite rules).
7. In the OSM Badge Supplier control panel, set the order URL to your landing page, e.g. `https://example.com/osm-order/`.
8. Test with OSM Launch test order.

Optional: enable Dry run to fetch and show mapping without adding products to the cart.

Optional relay: if direct OSM calls fail with an HTML “Blocked” response from your server IP, deploy `osm-relay-worker/`, set Relay URL and Relay secret in settings.

Optional: enable Admin debug panel to show the "Admin debug (OSM HTTP)" panel on the landing page (shop admins only; off by default).

== Empty basket button ==

A nonce-protected "Empty basket" button (with a confirm prompt) appears on the landing result and on the cart page. Placement:

* Classic cart, `[woocommerce_cart]` shortcode, Elementor Cart widget: added automatically above the cart via `woocommerce_before_cart`.
* Cart block (`woocommerce/cart`): added automatically after the block via `render_block`.
* Page-builder layouts where neither hook runs (e.g. a custom Elementor/Astra basket page): add a Shortcode widget containing `[osm_empty_basket]`. Optional attributes: `label`, `redirect`, `class`.

The button only renders when the basket has items. To turn off the automatic placement and use only the shortcode: `add_filter( 'osm_badge_supplier_auto_cart_empty_button', '__return_false' );`

== Frequently Asked Questions ==

= Where is client_id sent? =

Only in the POST body as `application/x-www-form-urlencoded` with the single field `client_id`. It is never placed in the request URL.

= What if stock_code is null? =

The badge is listed under Unavailable on the first landing page. The plugin never guesses a product from the badge name.

= Order link expired? =

OSM tokens expire in about five minutes. The plugin never stores the order URL. Ask the leader to go back to OSM and click through again.

= Why use a relay? =

Some hosting providers’ egress IPs are blocked by OSM. The optional relay forwards the same POST from an allowlisted network (for example Cloudflare Workers). The relay secret is never shown on the public landing page.

== Changelog ==

= 1.0.10 =
* Continue shopping: Scout Network and Scout Active Support no longer map to the Scouts category (fall back to Shop).

= 1.0.9 =
* Landing result buttons: Checkout (primary), Continue shopping (product category for the order's section: Squirrels/Beavers/Cubs/Scouts/Explorers; falls back to Shop, then home), Empty basket (admin). View cart removed.
* Empty basket is admin/testing only everywhere (landing, cart auto hooks, shortcode): shown and accepted only for manage_woocommerce or manage_options; server-side 403 otherwise. Open to all: `add_filter( 'osm_badge_supplier_empty_basket_public', '__return_true' );`
* Filters: osm_badge_supplier_section_key, osm_badge_supplier_section_category_slugs, osm_badge_supplier_badges_parent_slug, osm_badge_supplier_continue_shopping_url.

= 1.0.8 =
* Prevent fatal "Cannot redeclare function" when a second copy of the plugin folder is present (e.g. leftover from Upload Plugin / GitHub zip).

= 1.0.7 =
* Empty basket nonce is now tied to the WooCommerce customer session, so guests no longer share one nonce.

= 1.0.6 =
* Landing "Added to basket" table no longer appends the "(n)" level number after each badge name.
* New setting "Admin debug panel" (off by default) gates the Admin debug (OSM HTTP) panel.
* New "Empty basket" button (nonce + confirm) on the landing result and cart page; `[osm_empty_basket]` shortcode; auto-placement via `woocommerce_before_cart` and the `woocommerce/cart` block.

= 1.0.5 =
* Release tidy: documentation, GPLv2+, User-Agent uses site home URL, optional relay companion docs.

= 1.0.4 =
* Detect OSM IP block (HTML / x-blocked) instead of opaque invalid_json.
* Admin-only HTTP debug transient (code, content-type, body prefix, has_auth_guid) — never logs client_id or full order_url.
* Optional HTTPS relay URL for when origin egress is blocked by OSM.
* Identify User-Agent; do not follow bare-host 301 HTML.

= 1.0.3 =
* Handle object-shaped OSM error payloads (e.g. access-error-3) without casting arrays to string; map access errors to the expired-link message.

= 1.0.2 =
* Declare WooCommerce HPOS (custom_order_tables) and cart/checkout blocks compatibility.

= 1.0.1 =
* SKU lookup also tries spaces↔hyphens (OSM stock_code charset).

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.9 =
Empty basket becomes admin-only; adds Continue shopping on the landing page.

= 1.0.8 =
Safer activation when an old plugin folder is still installed.

= 1.0.7 =
Security hardening for the Empty basket nonce.

= 1.0.6 =
Adds Empty basket button/shortcode and makes the admin debug panel opt-in.

= 1.0.5 =
Documentation and release polish. Optional Cloudflare Worker relay companion included in the GitHub repository.
