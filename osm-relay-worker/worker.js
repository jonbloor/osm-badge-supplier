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
      if (!env.RELAY_SECRET || !secretsMatch(secret, env.RELAY_SECRET)) {
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

      if (!client_id || !isAllowedOrderPath(order_url)) {
        return json({ status: false, error: { message: 'Bad request', code: 'bad_request' } }, 400);
      }

      const target = 'https://www.onlinescoutmanager.co.uk' + order_url;
      const body = new URLSearchParams();
      body.set('client_id', client_id);

      const upstream = await globalThis.fetch(target, {
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
          },
        },
        500
      );
    }
  },
};

/**
 * Same path rule as OSM_Badge_Supplier_OSM_Client::is_valid_order_url().
 * Relative /v3/badges/suppliers/order/… only. No scheme, traversal, or control characters.
 *
 * @param {string} orderUrl
 * @return {boolean}
 */
export function isAllowedOrderPath(orderUrl) {
  if (typeof orderUrl !== 'string' || orderUrl.length === 0) {
    return false;
  }
  const prefix = '/v3/badges/suppliers/order/';
  if (!orderUrl.startsWith(prefix) || orderUrl.length <= prefix.length) {
    return false;
  }
  if (orderUrl.includes('..') || orderUrl.includes('\\') || orderUrl.includes('//')) {
    return false;
  }
  if (/[\u0000-\u001F\u007F]/.test(orderUrl)) {
    return false;
  }
  if (/%2e%2e/i.test(orderUrl) || /%2f%2f/i.test(orderUrl) || /%5c/i.test(orderUrl)) {
    return false;
  }
  return true;
}

/**
 * Length-sensitive compare that does not stop at the first differing character.
 *
 * @param {string} left
 * @param {string} right
 * @return {boolean}
 */
function secretsMatch(left, right) {
  const a = String(left);
  const b = String(right);
  if (a.length === 0 || a.length !== b.length) {
    return false;
  }
  let diff = 0;
  for (let i = 0; i < a.length; i++) {
    diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return diff === 0;
}

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
