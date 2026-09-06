/**
 * OSM Badge Supplier egress relay.
 * POST body: application/x-www-form-urlencoded OR JSON with client_id + order_url.
 * Header: X-OSM-Relay-Secret must match RELAY_SECRET.
 */
export default {
  async fetch(request, env) {
    try {
      if (request.method === 'OPTIONS') {
        return new Response(null, { status: 204, headers: cors() });
      }
      if (request.method !== 'POST') {
        return json({ status: false, error: { message: 'POST only', code: 'method' } }, 405);
      }

      const secret = request.headers.get('X-OSM-Relay-Secret') || '';
      if (!env.RELAY_SECRET || secret !== env.RELAY_SECRET) {
        return json({ status: false, error: { message: 'Unauthorized', code: 'auth' } }, 401);
      }

      const ct = (request.headers.get('content-type') || '').toLowerCase();
      let client_id = '';
      let order_url = '';

      if (ct.includes('application/json')) {
        const j = await request.json();
        client_id = String(j.client_id || '');
        order_url = String(j.order_url || '');
      } else {
        // Prefer raw urlencoded parse — more reliable than formData for x-www-form-urlencoded.
        const raw = await request.text();
        const params = new URLSearchParams(raw);
        client_id = String(params.get('client_id') || '');
        order_url = String(params.get('order_url') || '');
      }

      if (!client_id || !order_url.startsWith('/') || /:\/\//.test(order_url)) {
        return json({ status: false, error: { message: 'Bad request', code: 'bad_request' } }, 400);
      }
      if (!order_url.startsWith('/v3/badges/suppliers/order/')) {
        return json({ status: false, error: { message: 'Path not allowed', code: 'path' } }, 400);
      }

      const target = 'https://www.onlinescoutmanager.co.uk' + order_url;
      const body = new URLSearchParams();
      body.set('client_id', client_id);

      const upstream = await fetch(target, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'User-Agent': 'OSMBadgeSupplier-Relay/1.0',
        },
        body: body.toString(),
        redirect: 'manual',
      });

      const text = await upstream.text();
      const outCt = upstream.headers.get('content-type') || 'application/json';
      return new Response(text, {
        status: upstream.status,
        headers: Object.assign({ 'Content-Type': outCt }, cors()),
      });
    } catch (err) {
      return json(
        {
          status: false,
          error: {
            message: 'Relay exception',
            code: 'relay_exception',
            detail: String(err && err.message ? err.message : err),
          },
        },
        500
      );
    }
  },
};

function cors() {
  return {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type, X-OSM-Relay-Secret',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
  };
}

function json(obj, status) {
  status = status || 200;
  return new Response(JSON.stringify(obj), {
    status: status,
    headers: Object.assign({ 'Content-Type': 'application/json' }, cors()),
  });
}
