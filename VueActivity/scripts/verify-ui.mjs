import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.FRONTEND_URL || 'http://localhost:9000';
const screenshotDir = path.resolve(process.env.SCREENSHOT_DIR || fileURLToPath(new URL('../.local-screenshots/', import.meta.url)));
const backendCredentialPath = fileURLToPath(new URL('../../backend/.vercel/admin-credentials.env', import.meta.url));
let adminEmail = process.env.ADMIN_EMAIL;
let adminPassword = process.env.ADMIN_PASSWORD;
if (!adminEmail || !adminPassword) {
  try {
    const env = await readFile(backendCredentialPath, 'utf8');
    for (const line of env.split(/\r?\n/)) {
      const match = line.match(/^\s*(ADMIN_EMAIL|ADMIN_PASSWORD)\s*=\s*(.*?)\s*$/);
      if (!match) continue;
      const value = match[2].replace(/^(['"])(.*)\1$/, '$2');
      if (match[1] === 'ADMIN_EMAIL') adminEmail ||= value;
      if (match[1] === 'ADMIN_PASSWORD') adminPassword ||= value;
    }
  } catch { /* Credentials can be passed as environment variables. */ }
}
assert.ok(adminEmail && adminPassword, 'Set ADMIN_EMAIL and ADMIN_PASSWORD (or provide backend .vercel/admin-credentials.env).');
await mkdir(screenshotDir, { recursive: true });
const browser = await chromium.launch({ headless: true });
const errors = [];
const evidence = {};
const shot = name => path.join(screenshotDir, name);
const unique = `ui-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
const email = `${unique}@example.test`;
const password = `Qa-${Math.random().toString(36).slice(2, 12)}-9A!`;
const customerName = 'KaONTIME UI verification';
const phone = '09123456789';
const addressValue = `UI verification address ${unique}, Cebu City`;

async function waitForMenu(page) {
  await page.locator('.food-card').first().waitFor({ state: 'visible', timeout: 20000 });
}

async function signIn(page, emailValue, passwordValue) {
  await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
  await page.locator('input[type="email"]').fill(emailValue);
  await page.locator('input[type="password"]').fill(passwordValue);
  await page.getByRole('button', { name: /^sign in$/i }).click();
  await page.waitForURL(`${base}/`);
}

try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

  await page.goto(base, { waitUntil: 'networkidle' });
  await waitForMenu(page);
  await page.screenshot({ path: shot('home-desktop.png'), animations: 'disabled' });
  const productCard = page.locator('.food-card').filter({ hasText: 'Signature Angus Cheeseburger' }).first();
  const productName = (await productCard.locator('h3').innerText()).trim();

  await page.goto(`${base}/register`, { waitUntil: 'networkidle' });
  await page.locator('input[autocomplete="name"]').fill(customerName);
  await page.locator('input[type="email"]').fill(email);
  await page.locator('input[autocomplete="new-password"]').first().fill(password);
  await page.locator('input[autocomplete="new-password"]').nth(1).fill(password);
  const registerResponse = page.waitForResponse(r => r.url().endsWith('/api/auth/register') && r.request().method() === 'POST');
  await page.getByRole('button', { name: /create account/i }).click();
  const registered = await registerResponse;
  assert.equal(registered.status(), 201, `Register returned ${registered.status()}`);
  await page.waitForURL(`${base}/`);
  await waitForMenu(page);
  evidence.customerEmail = email;

  const favoriteButton = page.locator('.food-card').filter({ hasText: productName }).first().getByRole('button', { name: 'Save favorite' });
  const favoriteResponse = page.waitForResponse(r => r.url().includes('/api/favorites/') && r.request().method() === 'PUT');
  await favoriteButton.click();
  assert.ok((await favoriteResponse).ok(), 'Favorite save should succeed');
  await page.locator('.food-card').filter({ hasText: productName }).first().getByRole('button', { name: 'Remove favorite' }).waitFor();
  await page.getByRole('button', { name: /saved/i }).click();
  await page.locator('.food-card').first().waitFor();
  assert.equal(await page.locator('.food-card').count(), 1, 'Favorite filter should show saved dish');
  await page.getByRole('button', { name: /saved/i }).click();

  await page.goto(`${base}/orders`, { waitUntil: 'networkidle' });
  await page.getByRole('button', { name: 'Addresses' }).click();
  await page.getByLabel('Label').fill('UI check');
  await page.getByLabel('Recipient name').fill(customerName);
  await page.getByLabel('Phone').fill(phone);
  await page.getByLabel('Address', { exact: true }).fill(addressValue);
  await page.getByLabel('Make default address').check();
  const addressResponse = page.waitForResponse(r => r.url().endsWith('/api/addresses') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Save address' }).click();
  assert.ok((await addressResponse).ok(), 'Address creation should succeed');
  await page.getByRole('button', { name: 'Edit', exact: true }).click();
  await page.getByLabel('Label').fill('UI check edited');
  const updateAddress = page.waitForResponse(r => /\/api\/addresses\/\d+$/.test(r.url()) && r.request().method() === 'PUT');
  await page.getByRole('button', { name: 'Update address' }).click();
  assert.ok((await updateAddress).ok(), 'Address update should succeed');

  await page.goto(base, { waitUntil: 'networkidle' });
  await waitForMenu(page);
  await page.locator('.food-card').filter({ hasText: productName }).first().getByRole('button', { name: /add to order/i }).click();
  await page.locator('.order-pill').click();
  await page.getByPlaceholder('Full name').fill(customerName);
  await page.getByPlaceholder('09XX XXX XXXX').fill(phone);
  const addressSelect = page.locator('select').filter({ has: page.locator('option', { hasText: 'UI check edited' }) });
  await addressSelect.selectOption({ label: `UI check edited · ${addressValue}` });
  assert.equal(await page.getByPlaceholder('House / unit, street, barangay, city').inputValue(), addressValue, 'Saved address should fill checkout address');
  await page.screenshot({ path: shot('checkout-desktop.png') });
  const orderResponse = page.waitForResponse(r => r.url().endsWith('/api/orders') && r.request().method() === 'POST');
  await page.getByRole('button', { name: /place order/i }).click();
  const orderRes = await orderResponse;
  assert.ok([200, 201].includes(orderRes.status()), `Order returned ${orderRes.status()}`);
  const order = (await orderRes.json()).data;
  evidence.orderNumber = order.orderNumber;
  assert.equal(order.status, 'Pending');
  await page.locator('.success-card').waitFor();
  await page.screenshot({ path: shot('receipt-desktop.png') });

  await page.reload({ waitUntil: 'networkidle' });
  assert.ok(await page.getByText(customerName, { exact: true }).count() > 0 || await page.getByText('My orders', { exact: true }).count() > 0, 'Customer session should persist through reload');
  await page.goto(`${base}/orders`, { waitUntil: 'networkidle' });
  await page.getByRole('button', { name: 'Order history' }).click();
  await page.getByText(order.orderNumber, { exact: true }).waitFor();
  await page.locator('summary').filter({ hasText: 'View receipt' }).first().click();
  await page.getByText(addressValue, { exact: false }).waitFor();
  await page.screenshot({ path: shot('orders-desktop.png'), fullPage: true });
  await page.getByRole('button', { name: 'Favorites' }).click();
  await page.getByText(productName, { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Addresses' }).click();
  await page.locator('.address-row').filter({ hasText: 'UI check edited' }).waitFor();
  await page.screenshot({ path: shot('account-desktop.png'), fullPage: true });
  await page.getByRole('button', { name: 'Order history' }).click();
  const cancelResponse = page.waitForResponse(r => r.url().endsWith(`/api/orders/${encodeURIComponent(order.orderNumber)}/cancel`) && r.request().method() === 'PATCH');
  await page.getByRole('button', { name: 'Cancel pending order' }).click();
  assert.ok((await cancelResponse).ok(), 'Pending order should cancel successfully');
  await page.getByText('Cancelled', { exact: true }).waitFor();

  await page.getByRole('button', { name: 'Addresses' }).click();
  const savedAddressRow = page.locator('.address-row').filter({ hasText: 'UI check edited' });
  const deleteAddress = page.waitForResponse(r => /\/api\/addresses\/\d+$/.test(r.url()) && r.request().method() === 'DELETE');
  await savedAddressRow.getByRole('button', { name: 'Delete' }).click();
  assert.ok((await deleteAddress).ok(), 'Address deletion should succeed');
  const logoutResponse = page.waitForResponse(r => r.url().endsWith('/api/auth/logout') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Sign out' }).click();
  assert.ok((await logoutResponse).ok(), 'Logout endpoint should succeed');
  await page.waitForURL(`${base}/`);
  assert.equal(await page.getByText('Sign in', { exact: true }).count(), 1, 'Signed-out nav should return');
  assert.equal(await page.getByRole('button', { name: /^Saved$/ }).count(), 1, 'Signed-out UI should clear private favorite count');
  await signIn(page, email, password);
  await page.goto(`${base}/orders`, { waitUntil: 'networkidle' });
  await page.getByRole('button', { name: 'Favorites' }).click();
  await page.getByText(productName, { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Remove favorite' }).click();
  await page.getByText('No saved dishes yet').waitFor();
  await page.getByRole('button', { name: 'Order history' }).click();
  await page.getByText(order.orderNumber, { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForURL(`${base}/`);

  await signIn(page, email, password);
  await waitForMenu(page);
  await page.locator('.food-card').filter({ hasText: productName }).first().getByRole('button', { name: /add to order/i }).click();
  await page.locator('.order-pill').click();
  await page.getByPlaceholder('Full name').fill(customerName);
  await page.getByPlaceholder('09XX XXX XXXX').fill(phone);
  await page.getByPlaceholder('House / unit, street, barangay, city').fill(addressValue);
  const progressionResponse = page.waitForResponse(r => r.url().endsWith('/api/orders') && r.request().method() === 'POST');
  await page.getByRole('button', { name: /place order/i }).click();
  const progressionOrderResponse = await progressionResponse;
  assert.ok([200, 201].includes(progressionOrderResponse.status()), 'Second real order should be created for admin status progression');
  const progressionOrder = (await progressionOrderResponse.json()).data;
  evidence.progressionOrder = progressionOrder.orderNumber;
  await page.getByRole('button', { name: 'Back to the menu' }).click();
  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForURL(`${base}/`);

  await signIn(page, adminEmail, adminPassword);
  await page.screenshot({ path: shot('home-admin.png'), fullPage: true });
  await page.goto(`${base}/admin`, { waitUntil: 'networkidle' });
  await page.getByRole('heading', { name: 'Admin workspace' }).waitFor();
  await page.screenshot({ path: shot('admin-menu.png'), fullPage: true });

  await page.getByRole('button', { name: 'Orders', exact: true }).click();
  const managedOrder = page.locator('.order-card').filter({ hasText: progressionOrder.orderNumber });
  await managedOrder.waitFor();
  const statusSelect = managedOrder.locator('select');
  for (const status of ['Preparing', 'Out for Delivery', 'Delivered']) {
    const statusResponse = page.waitForResponse(r => r.url().endsWith(`/api/admin/orders/${encodeURIComponent(progressionOrder.orderNumber)}/status`) && r.request().method() === 'PATCH');
    await statusSelect.selectOption({ label: status });
    assert.ok((await statusResponse).ok(), `${status} transition should succeed`);
  }
  await page.screenshot({ path: shot('admin-orders.png'), fullPage: true });

  await page.getByRole('button', { name: 'Menu', exact: true }).click();
  const menuRow = page.locator('.admin-row').filter({ hasText: productName }).first();
  await menuRow.getByRole('button', { name: 'Edit' }).click();
  const changedName = `${productName} UI`;
  await page.getByRole('textbox', { name: 'Name', exact: true }).fill(changedName);
  const productUpdate = page.waitForResponse(r => /\/api\/admin\/products\/\d+$/.test(r.url()) && r.request().method() === 'PUT');
  await page.getByRole('button', { name: 'Save changes' }).click();
  assert.ok((await productUpdate).ok(), 'Admin product edit should persist');
  await page.getByText(changedName, { exact: true }).waitFor();
  const changedRow = page.locator('.admin-row').filter({ hasText: changedName }).first();
  await changedRow.getByRole('button', { name: 'Edit' }).click();
  await page.getByRole('textbox', { name: 'Name', exact: true }).fill(productName);
  const restoreProduct = page.waitForResponse(r => /\/api\/admin\/products\/\d+$/.test(r.url()) && r.request().method() === 'PUT');
  await page.getByRole('button', { name: 'Save changes' }).click();
  assert.ok((await restoreProduct).ok(), 'Product name cleanup should persist');

  await page.getByRole('button', { name: 'Reports', exact: true }).click();
  await page.getByText('ALL ORDERS', { exact: true }).waitFor();
  await page.screenshot({ path: shot('admin-reports.png'), fullPage: true });

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
  mobile.on('pageerror', error => errors.push(error.message));
  await mobile.goto(base, { waitUntil: 'networkidle' });
  await waitForMenu(mobile);
  assert.ok(await mobile.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Mobile storefront must not overflow');
  await mobile.screenshot({ path: shot('home-mobile.png'), animations: 'disabled' });
  assert.deepEqual(errors, [], 'No uncaught page/console errors');
  console.log(JSON.stringify({ passed: true, orderNumber: evidence.orderNumber, screenshots: screenshotDir, checks: ['register', 'favorite CRUD', 'address CRUD/default/use', 'real COD order', 'receipt', 'customer cancel', 'session reload/logout/login', 'admin product edit', 'admin order transitions', 'reports', 'mobile overflow'] }, null, 2));
} finally {
  await browser.close();
}
