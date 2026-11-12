/**
 * Relay checks. globalThis.fetch is replaced so these tests never call OSM.
 * Path cases match tests/ClientTest.php.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import worker, { isAllowedOrderPath } from './worker.js';

const SECRET = 'relay-secret-value';

test('order paths match the plugin rules', () => {
  const valid = [
    '/v3/badges/suppliers/order/abc',
    '/v3/badges/suppliers/order/abc?_auth_guid=11111111-2222-3333-4444-555555555555',
    '/v3/badges/suppliers/order/tok%2Fpart',
  ];
  const invalid = [
    '',
    '/etc/passwd',
    'https://www.onlinescoutmanager.co.uk/v3/badges/suppliers/order/abc',
    '//www.onlinescoutmanager.co.uk/v3/badges/suppliers/order/abc',
    '/v3/badges/suppliers/order/../../secret',
    '/v3/badges/suppliers/order/%2e%2e/secret',
    '/v3/badges/suppliers/order/%2f%2fsecret',
    '/v3/badges/suppliers/order/%5csecret',
    '/v3/badges/suppliers/order/abc\nSet-Cookie: x',
    '/v3/badges/suppliers/order/',
    '/v3/badges/suppliers/order/abc\\evil',
  ];
  for (const path of valid) {
    assert.equal(isAllowedOrderPath(path), true, path);
  }
  for (const path of invalid) {
    assert.equal(isAllowedOrderPath(path), false, path);
  }
});

test('OPTIONS and non-POST do not call upstream', async () => {
  const guard = refuseUpstream();
  const options = await worker.fetch(new Request('https://relay.test/', { method: 'OPTIONS' }), {
    RELAY_SECRET: SECRET,
  });
  assert.equal(options.status, 204);
  const get = await worker.fetch(new Request('https://relay.test/', { method: 'GET' }), {
    RELAY_SECRET: SECRET,
  });
  assert.equal(get.status, 405);
  assert.equal(guard.called, false);
});

test('a bad secret is refused before any upstream call', async () => {
  const guard = refuseUpstream();
  const response = await postForm(
    { client_id: 'client-secret-value', order_url: '/v3/badges/suppliers/order/abc' },
    'wrong-secret-value'
  );
  assert.equal(response.status, 401);
  assert.equal(guard.called, false);
});

test('traversal is rejected and OSM is not called', async () => {
  const guard = refuseUpstream();
  const response = await postForm({
    client_id: 'client-secret-value',
    order_url: '/v3/badges/suppliers/order/../../secret',
  });
  assert.equal(response.status, 400);
  const payload = await response.json();
  assert.equal(payload.error.code, 'bad_request');
  assert.equal(guard.called, false);
});

test('a valid order is forwarded with client_id only in the body', async () => {
  let seen = null;
  globalThis.fetch = async (url, options) => {
    seen = { url: String(url), options };
    return new Response('{"status":true,"data":{"badges":[]}}', {
      status: 200,
      headers: { 'content-type': 'application/json' },
    });
  };

  const orderUrl = '/v3/badges/suppliers/order/abc?_auth_guid=11111111-2222-3333-4444-555555555555';
  const response = await postForm({
    client_id: 'client-secret-value',
    order_url: orderUrl,
  });

  assert.equal(response.status, 200);
  assert.ok(seen, 'upstream was not called');
  assert.equal(seen.url, 'https://www.onlinescoutmanager.co.uk' + orderUrl);
  assert.equal(seen.url.includes('client-secret-value'), false);
  assert.equal(seen.options.redirect, 'manual');
  assert.match(seen.options.body, /^client_id=client-secret-value$/);
  const text = await response.text();
  assert.equal(text.includes('client-secret-value'), false);
});

test('JSON bodies use the same path rule', async () => {
  const guard = refuseUpstream();
  const response = await worker.fetch(
    new Request('https://relay.test/', {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-osm-relay-secret': SECRET,
      },
      body: JSON.stringify({
        client_id: 'client-secret-value',
        order_url: 'https://evil.test/v3/badges/suppliers/order/abc',
      }),
    }),
    { RELAY_SECRET: SECRET }
  );
  assert.equal(response.status, 400);
  assert.equal(guard.called, false);
});

test('exceptions do not include a detail field', async () => {
  const guard = refuseUpstream();
  const response = await worker.fetch(
    new Request('https://relay.test/', {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-osm-relay-secret': SECRET,
      },
      body: '{',
    }),
    { RELAY_SECRET: SECRET }
  );
  assert.equal(response.status, 500);
  const payload = await response.json();
  assert.equal(payload.error.code, 'relay_exception');
  assert.equal(Object.prototype.hasOwnProperty.call(payload.error, 'detail'), false);
  assert.equal(guard.called, false);
});

function refuseUpstream() {
  const guard = { called: false };
  globalThis.fetch = async () => {
    guard.called = true;
    throw new Error('refusing live OSM call');
  };
  return guard;
}

function postForm(fields, secret = SECRET) {
  const body = new URLSearchParams();
  for (const [key, value] of Object.entries(fields)) {
    body.set(key, value);
  }
  return worker.fetch(
    new Request('https://relay.test/', {
      method: 'POST',
      headers: {
        'content-type': 'application/x-www-form-urlencoded',
        'x-osm-relay-secret': secret,
      },
      body: body.toString(),
    }),
    { RELAY_SECRET: SECRET }
  );
}
