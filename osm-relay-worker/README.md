# OSM Badge Supplier relay (Cloudflare Worker)

Optional companion for hosts whose egress IP is blocked by Online Scout Manager.

## Deploy

```bash
npx wrangler deploy
npx wrangler secret put RELAY_SECRET
```

Configure the same URL and secret in WooCommerce → Settings → OSM Badges.

## Protocol

- `POST` `application/x-www-form-urlencoded` or JSON: `client_id`, `order_url` (relative path starting with `/v3/badges/suppliers/order/`).
- Header: `X-OSM-Relay-Secret` must match the Worker secret.
- Upstream: `POST https://www.onlinescoutmanager.co.uk{order_url}` with only `client_id` in the body.
