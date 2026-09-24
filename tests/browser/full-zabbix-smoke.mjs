import { chromium } from 'playwright';

const baseUrl = process.env.ZTUM_SMOKE_BASE_URL || 'http://127.0.0.1:18080';
const apiUrl = baseUrl + '/api_jsonrpc.php';
const adminUser = process.env.ZTUM_SMOKE_USER || 'Admin';
const adminPassword = process.env.ZTUM_SMOKE_PASSWORD || 'zabbix';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function waitForFrontend() {
	for (let attempt = 1; attempt <= 90; attempt++) {
		try {
			const response = await fetch(baseUrl + '/');
			if (response.ok) {
				return;
			}
		}
		catch {
			// Container is still starting.
		}
		await sleep(2000);
	}
	throw new Error('Zabbix frontend did not become ready in time.');
}

let requestId = 1;
async function api(method, params = {}, auth = undefined) {
	const request = async (useBearer) => {
		const payload = {
			jsonrpc: '2.0',
			method,
			params,
			id: requestId++
		};
		const headers = {'Content-Type': 'application/json-rpc'};

		if (auth) {
			if (useBearer) {
				headers.Authorization = 'Bearer ' + auth;
			}
			else {
				payload.auth = auth;
			}
		}

		const response = await fetch(apiUrl, {
			method: 'POST',
			headers,
			body: JSON.stringify(payload)
		});
		if (!response.ok) {
			throw new Error(`API HTTP ${response.status} for ${method}`);
		}
		return response.json();
	};

	let data = await request(false);

	// Zabbix 8 rejects the legacy top-level JSON-RPC auth field and expects
	// the API token/session in an Authorization: Bearer header.
	if (auth && data?.error
			&& String(data.error?.data || '').includes('unexpected parameter "auth"')) {
		data = await request(true);
	}

	if (data.error) {
		throw new Error(`${method}: ${JSON.stringify(data.error)}`);
	}
	return data.result;
}

async function loginApiWithRetry() {
	let lastError = null;

	for (let attempt = 1; attempt <= 90; attempt++) {
		try {
			return await api('user.login', {
				username: adminUser,
				password: adminPassword
			});
		}
		catch (error) {
			lastError = error;
			await sleep(2000);
		}
	}

	throw new Error(
		'Zabbix API/database initialization did not become ready in time: '
			+ (lastError?.message || 'unknown error')
	);
}

async function ensureModule(auth) {
	const existing = await api('module.get', {
		output: ['moduleid', 'id', 'relative_path', 'status'],
		filter: {id: ['kmansur_zabbix_template_update_manager']}
	}, auth);

	if (Array.isArray(existing) && existing.length > 0) {
		const current = existing[0];
		if (String(current.status) !== '1') {
			await api('module.update', {
				moduleid: current.moduleid,
				status: 1
			}, auth);
		}
		return;
	}

	await api('module.create', {
		id: 'kmansur_zabbix_template_update_manager',
		relative_path: 'modules/zabbix-template-update-manager',
		status: 1
	}, auth);
}

async function setTheme(auth, theme) {
	const users = await api('user.get', {
		output: ['userid', 'username', 'alias', 'theme'],
		filter: {username: [adminUser]}
	}, auth).catch(async () => api('user.get', {
		output: ['userid', 'alias', 'theme'],
		filter: {alias: [adminUser]}
	}, auth));

	if (!Array.isArray(users) || users.length !== 1) {
		throw new Error('Unable to resolve the smoke-test Super Admin user.');
	}

	await api('user.update', {
		userid: users[0].userid,
		theme
	}, auth);
}

async function loginUi(page) {
	await page.goto(baseUrl + '/', {waitUntil: 'domcontentloaded', timeout: 120000});
	const user = page.locator('input[name="name"]');
	const password = page.locator('input[name="password"]');
	await user.waitFor({state: 'visible', timeout: 60000});
	await user.fill(adminUser);
	await password.fill(adminPassword);
	await Promise.all([
		page.waitForLoadState('domcontentloaded'),
		page.locator('button[type="submit"], input[type="submit"]').first().click()
	]);
}

async function assertCatalog(page, label) {
	const response = await page.goto(
		baseUrl + '/zabbix.php?action=ztum.templates',
		{waitUntil: 'domcontentloaded', timeout: 120000}
	);
	if (!response || !response.ok()) {
		throw new Error(`${label}: catalog HTTP status ${response?.status() ?? 'none'}`);
	}
	await page.getByText('Zabbix Template Update Manager', {exact: false}).first()
		.waitFor({state: 'visible', timeout: 120000});

	const body = await page.locator('body').innerText();
	for (const forbidden of ['PHP Fatal error', 'Undefined constant', 'Uncaught Error', 'HTTP ERROR 500']) {
		if (body.includes(forbidden)) {
			throw new Error(`${label}: frontend contains fatal marker: ${forbidden}`);
		}
	}

	const nameFilter = page.locator('input[name="filter_name"]');
	await nameFilter.waitFor({state: 'visible', timeout: 60000});
	await nameFilter.fill('Linux');
	const apply = page.locator('button[name="filter_set"], input[name="filter_set"]').first();
	await Promise.all([
		page.waitForLoadState('domcontentloaded'),
		apply.click()
	]);
	await page.getByText('Zabbix Template Update Manager', {exact: false}).first()
		.waitFor({state: 'visible', timeout: 60000});
}

await waitForFrontend();

const auth = await loginApiWithRetry();
await ensureModule(auth);

const browser = await chromium.launch({headless: true});
const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
const errors = [];
page.on('pageerror', (error) => errors.push('pageerror: ' + error.message));
page.on('response', (response) => {
	if (response.status() < 400) {
		return;
	}

	const url = response.url();
	const isModuleResource = url.includes('zabbix-template-update-manager')
		|| url.includes('action=ztum.');
	if (isModuleResource) {
		errors.push(`HTTP ${response.status()}: ${url}`);
	}
});

try {
	await loginUi(page);

	await setTheme(auth, 'dark-theme');
	await assertCatalog(page, 'dark-theme');
	await page.screenshot({path: 'runtime-smoke-dark.png', fullPage: true});

	await setTheme(auth, 'blue-theme');
	await assertCatalog(page, 'blue-theme');
	await page.screenshot({path: 'runtime-smoke-light.png', fullPage: true});

	const historyResponse = await page.goto(
		baseUrl + '/zabbix.php?action=ztum.operations',
		{waitUntil: 'domcontentloaded', timeout: 60000}
	);
	if (!historyResponse || !historyResponse.ok()) {
		throw new Error(`operation history HTTP status ${historyResponse?.status() ?? 'none'}`);
	}
	await page.getByText('ZTUM operation history', {exact: false}).first()
		.waitFor({state: 'visible', timeout: 60000});

	if (errors.length > 0) {
		throw new Error('Browser errors detected: ' + errors.join(' | '));
	}

	console.log('Full Zabbix frontend smoke passed for light/dark catalog rendering and operation history.');
}
finally {
	await browser.close();
}
