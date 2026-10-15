# OSM Badge Supplier

WordPress / WooCommerce plugin that receives [Online Scout Manager](https://www.onlinescoutmanager.co.uk/) Badge Supplier orders, maps `stock_code` to product SKUs, and stages the cart.

**License:** [GPLv2 or later](LICENSE)

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+

## Install (WordPress)

1. Download or build `osm-badge-supplier.zip` (plugin files at the zip root, or this folder excluding `osm-relay-worker/` and `README.md` / `LICENSE` as needed).
2. Plugins → Add New → Upload Plugin → Activate.
3. WooCommerce → Settings → **OSM Badges**: set Client ID, landing slug, OSM host.
4. Settings → Permalinks → Save.
5. In OSM, set the Badge Supplier order URL to `https://your-shop.example/osm-order/` (trailing slash recommended).

See `readme.txt` for the full WordPress.org-style readme.

## Optional OSM relay (`osm-relay-worker/`)

If your web server’s outbound IP is blocked by OSM (HTML response with `x-blocked: true`), deploy the Cloudflare Worker in `osm-relay-worker/`:

```bash
cd osm-relay-worker
npx wrangler deploy
npx wrangler secret put RELAY_SECRET
```

Then in WooCommerce → OSM Badges set **OSM relay URL** to your `https://….workers.dev` URL and the same **OSM relay secret**.

The Worker accepts POST with `client_id` + `order_url` and header `X-OSM-Relay-Secret`, and forwards to OSM with `client_id` only in the body.

## Empty basket button

- **Admin/testing only by default** (manage_woocommerce or manage_options), label "Empty basket (admin)", enforced server-side (403). Open to all: `add_filter( 'osm_badge_supplier_empty_basket_public', '__return_true' );`

- Shown automatically on the `/osm-order/` result and on carts via `woocommerce_before_cart` (classic / shortcode / Elementor Cart widget) and `render_block` for `woocommerce/cart`.
- For custom Elementor/Astra basket pages where those hooks do not run, add a **Shortcode** widget with `[osm_empty_basket]` (attrs: `label`, `redirect`, `class`).
- Disable auto placement: `add_filter( 'osm_badge_supplier_auto_cart_empty_button', '__return_false' );`

## Security notes

- Never commit Client ID, relay secrets, or live order URLs.
- Admin-only debug on the landing page is off by default (WooCommerce → OSM Badges → Admin debug panel) and never prints `client_id` or the full order URL.
- Order tokens expire in ~5 minutes and are not stored.

## Development

Plugin PHP entry: `osm-badge-supplier.php`. Landing rewrite slug defaults to `osm-order`.
