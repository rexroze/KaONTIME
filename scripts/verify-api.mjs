import assert from 'node:assert/strict';
import { randomUUID, randomBytes } from 'node:crypto';

const base = (process.env.API_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const run = `${Date.now()}-${randomBytes(3).toString('hex')}`;
const password = randomBytes(24).toString('base64url');
let checks = 0;

async function request(path, { method = 'GET', token, body, headers = {}, status = 200 } = {}) {
  const response = await fetch(`${base}${path}`, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}), ...headers },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const text = await response.text();
  assert.equal(response.status, status, `${method} ${path}: expected ${status}, received ${response.status}; ${text.slice(0, 200)}`);
  checks++;
  return text ? JSON.parse(text) : null;
}

async function customer(suffix) {
  const email = `verification-${run}-${suffix}@example.com`;
  const result = await request('/auth/register', { method: 'POST', status: 201, body: { name: 'Verification Customer', email, password, password_confirmation: password, isAdmin: true, is_admin: true } });
  assert.equal(result.data.user.isAdmin, false, 'Registration cannot grant administrator privileges');
  assert.ok(result.data.token);
  return { token: result.data.token, email };
}

assert.equal((await request('/hello')).message, 'Hello World');
assert.equal((await request('/health')).database, 'connected');
const menu = (await request('/menu')).data;
const product = menu.find(item => item.available && item.stock >= 2);
assert.ok(product, 'A stocked menu item is needed for verification');
await request('/orders', { status: 401 });
const a = await customer('a');
const b = await customer('b');
await request('/admin/orders', { token: a.token, status: 403 });
await request('/admin/reports', { token: b.token, status: 403 });
await request(`/favorites/${product.id}`, { method: 'PUT', token: a.token });
assert.ok((await request('/favorites', { token: a.token })).data.some(item => item.id === product.id));
assert.equal((await request('/favorites', { token: b.token })).data.length, 0);

const address = (await request('/addresses', { method: 'POST', token: a.token, status: 201, body: {
  label: 'Verification', recipientName: 'Verification Customer', phone: '09123456789', address: 'Classroom test — no real delivery', isDefault: true,
} })).data;
assert.ok((await request('/addresses', { token: a.token })).data.some(item => item.id === address.id));
await request(`/addresses/${address.id}`, { method: 'PUT', token: b.token, status: 404, body: { label: 'Not allowed', recipientName: 'Other', phone: '09123456789', address: 'Other', isDefault: false } });
await request(`/addresses/${address.id}`, { method: 'DELETE', token: b.token, status: 404 });

const key = randomUUID();
const payload = { customerName: 'Verification Customer', customerPhone: '09123456789', deliveryAddress: 'Classroom test — no real delivery', paymentMethod: 'COD', items: [{ productId: product.id, quantity: 1 }], totalAmount: 1, deliveryFee: 0 };
const order = (await request('/orders', { method: 'POST', token: a.token, status: 201, headers: { 'Idempotency-Key': key }, body: payload })).data;
assert.equal(order.subtotal, Number(product.price));
assert.equal(order.deliveryFee, 50);
assert.equal(order.totalAmount, Number(product.price) + 50);
const replay = (await request('/orders', { method: 'POST', token: a.token, headers: { 'Idempotency-Key': key }, body: payload })).data;
assert.equal(replay.orderNumber, order.orderNumber);
await request('/orders', { method: 'POST', token: a.token, status: 409, headers: { 'Idempotency-Key': key }, body: { ...payload, items: [{ productId: product.id, quantity: 2 }] } });
assert.equal((await request('/orders', { token: a.token })).data.length, 1);
assert.equal((await request('/orders', { token: b.token })).data.length, 0);
await request(`/orders/${order.orderNumber}`, { token: b.token, status: 404 });
await request(`/orders/${order.orderNumber}/cancel`, { method: 'PATCH', token: b.token, status: 404 });
assert.equal((await request(`/orders/${order.orderNumber}/cancel`, { method: 'PATCH', token: a.token })).data.status, 'Cancelled');
assert.equal((await request('/menu')).data.find(item => item.id === product.id).stock, product.stock, 'Cancellation restores stock exactly once');
await request(`/orders/${order.orderNumber}/cancel`, { method: 'PATCH', token: a.token, status: 409 });
assert.equal((await request('/menu')).data.find(item => item.id === product.id).stock, product.stock, 'Repeated cancellation cannot inflate stock');

await request('/auth/logout', { method: 'POST', token: a.token });
await request('/auth/me', { token: a.token, status: 401 });
const signedIn = (await request('/auth/login', { method: 'POST', body: { email: a.email, password } })).data;
assert.ok((await request('/favorites', { token: signedIn.token })).data.some(item => item.id === product.id));
assert.ok((await request('/addresses', { token: signedIn.token })).data.some(item => item.id === address.id));

if (process.env.ADMIN_EMAIL && process.env.ADMIN_PASSWORD) {
  const admin = (await request('/auth/login', { method: 'POST', body: { email: process.env.ADMIN_EMAIL, password: process.env.ADMIN_PASSWORD } })).data;
  assert.equal(admin.user.isAdmin, true);
  assert.ok(Array.isArray((await request('/admin/products', { token: admin.token })).data));
  assert.ok((await request('/admin/orders', { token: admin.token })).data.some(item => item.orderNumber === order.orderNumber));
  const report = (await request('/admin/reports', { token: admin.token })).data;
  assert.ok(report.totalOrders >= 1);
  assert.ok(Array.isArray(report.topDishes));
  assert.ok(Array.isArray(report.lowStock));
  const managed = (await request('/orders', { method: 'POST', token: signedIn.token, status: 201, headers: { 'Idempotency-Key': randomUUID() }, body: payload })).data;
  assert.equal((await request(`/admin/orders/${managed.orderNumber}/status`, { method: 'PATCH', token: admin.token, body: { status: 'Preparing' } })).data.status, 'Preparing');
  await request(`/orders/${managed.orderNumber}/cancel`, { method: 'PATCH', token: signedIn.token, status: 409 });
  assert.equal((await request(`/admin/orders/${managed.orderNumber}/status`, { method: 'PATCH', token: admin.token, body: { status: 'Cancelled' } })).data.status, 'Cancelled');
  assert.equal((await request('/menu')).data.find(item => item.id === product.id).stock, product.stock);
  const draft = { name: `Verification dish ${run}`, category: 'Verification', price: 99, prepTime: '10 min', description: 'Temporary integration test item', image: 'images/burger.jpg', available: false, stock: 3, rating: 0, reviewsCount: 0 };
  const createdProduct = (await request('/admin/products', { method: 'POST', token: admin.token, status: 201, body: draft })).data;
  const editedProduct = (await request(`/admin/products/${createdProduct.id}`, { method: 'PUT', token: admin.token, body: { ...draft, prepTime: '12 min', stock: 5 } })).data;
  assert.equal(editedProduct.prepTime, '12 min');
  assert.equal(editedProduct.stock, 5);
  await request(`/admin/products/${createdProduct.id}`, { method: 'DELETE', token: admin.token });
  await request('/auth/logout', { method: 'POST', token: admin.token });
}
await request(`/favorites/${product.id}`, { method: 'DELETE', token: signedIn.token });
await request(`/addresses/${address.id}`, { method: 'DELETE', token: signedIn.token });
await request('/auth/logout', { method: 'POST', token: signedIn.token });
await request('/auth/logout', { method: 'POST', token: b.token });
console.log(JSON.stringify({ passed: true, checks, api: base, orderNumber: order.orderNumber, verified: ['authentication', 'role protection', 'customer isolation', 'favorites', 'addresses', 'authoritative totals', 'idempotency', 'cancellation stock restoration', 'logout revocation', ...(process.env.ADMIN_EMAIL ? ['admin reports', 'admin product CRUD', 'customer/admin cancellation permissions'] : [])] }, null, 2));
