const baseUrl = process.env.IUA_BASE_URL || 'http://localhost:8888';

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

async function login(username, password) {
	const body = new URLSearchParams({
		log: username,
		pwd: password,
		'wp-submit': 'Log In',
		redirect_to: `${baseUrl}/wp-admin/`,
		testcookie: '1'
	});
	const response = await fetch(`${baseUrl}/wp-login.php`, {
		method: 'POST',
		redirect: 'manual',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			Cookie: 'wordpress_test_cookie=WP%20Cookie%20check'
		},
		body
	});
	const cookies = response.headers.getSetCookie().map((value) => value.split(';', 1)[0]);

	assert(response.status === 302, `Login for ${username} returned ${response.status}.`);
	assert(cookies.some((cookie) => cookie.startsWith('wordpress_logged_in_')), `Login cookie missing for ${username}.`);

	return cookies.join('; ');
}

async function fetchAdminState(cookie) {
	const response = await fetch(`${baseUrl}/wp-admin/upload.php?page=iua-audit`, {
		headers: { Cookie: cookie }
	});
	const html = await response.text();
	const configMatch = html.match(/var IUAAdmin = (\{.*?\});/s);
	const idMatch = html.match(/class="button button-secondary iua-mark-used" data-id="(\d+)"/);

	assert(response.status === 200, `Plugin admin page returned ${response.status}.`);
	assert(configMatch, 'Localized AJAX configuration was not found.');
	assert(idMatch, 'AJAX attachment fixture was not rendered.');

	return {
		config: JSON.parse(configMatch[1]),
		attachmentId: Number.parseInt(idMatch[1], 10)
	};
}

async function ajax(cookie, fields, method = 'POST') {
	const body = new URLSearchParams(fields);
	const url = method === 'GET'
		? `${baseUrl}/wp-admin/admin-ajax.php?${body}`
		: `${baseUrl}/wp-admin/admin-ajax.php`;
	const response = await fetch(url, {
		method,
		headers: {
			Cookie: cookie,
			...(method === 'POST' ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {})
		},
		...(method === 'POST' ? { body } : {})
	});
	const text = await response.text();
	let payload;

	try {
		payload = JSON.parse(text);
	} catch {
		throw new Error(`AJAX response was not JSON (${response.status}): ${text.slice(0, 120)}`);
	}

	return { response, payload };
}

const adminCookie = await login('admin', 'password');
const editorCookie = await login('iua-ajax-editor', 'iua-ajax-editor-password');
const { config, attachmentId } = await fetchAdminState(adminCookie);
const endpoint = {
	action: 'iua_mark_manual_used',
	nonce: config.nonces.mark_manual,
	id: String(attachmentId)
};

let result = await ajax('', endpoint);
assert(result.response.status === 401 && result.payload.success === false, 'Unauthenticated request was not rejected with JSON 401.');

result = await ajax(editorCookie, endpoint);
assert(result.response.status === 403 && result.payload.success === false, 'Editor request was not rejected with JSON 403.');

result = await ajax(adminCookie, { ...endpoint, nonce: '' });
assert(result.response.status === 403 && result.payload.success === false, 'Missing nonce was not rejected with JSON 403.');

result = await ajax(adminCookie, { ...endpoint, nonce: config.nonces.unmark_manual });
assert(result.response.status === 403 && result.payload.success === false, 'Action-specific nonce separation failed.');

result = await ajax(adminCookie, endpoint, 'GET');
assert(result.response.status === 405 && result.payload.success === false, 'GET request was not rejected with JSON 405.');

result = await ajax(adminCookie, { ...endpoint, id: `${attachmentId}invalid` });
assert(result.response.status === 400 && result.payload.success === false, 'Malformed attachment ID was not rejected with JSON 400.');

result = await ajax(adminCookie, endpoint);
assert(result.response.status === 200 && result.payload.success === true, 'Authorized manual mark did not succeed.');
assert(result.payload.data.id === attachmentId, 'Authorized manual mark returned the wrong attachment ID.');

result = await ajax(adminCookie, {
	action: 'iua_mark_manual_used_bulk',
	nonce: config.nonces.mark_manual_bulk,
	'ids[0][nested]': String(attachmentId)
});
assert(result.response.status === 400 && result.payload.success === false, 'Malformed bulk array was not rejected with JSON 400.');

console.log(JSON.stringify({ result: 'pass', assertions: 8, attachmentId }));
