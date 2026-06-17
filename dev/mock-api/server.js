'use strict';

// Fake serp.cheap API for local plugin testing. Implements POST /v1/rank and
// POST /v1/search with the real response shape: full fake organic SERP, the
// tracked URL placed at its rank, matches, related searches, people-also-ask,
// X-API-Key auth, and a per-key in-memory credit balance. Deterministic (stable
// per url+keyword) with day-to-day drift; pass `_day` in the body to fetch a
// historical day (the plugin uses this to backfill sparklines).

const http = require('http');

const PORT = process.env.PORT || 8090;
const balances = Object.create(null); // api-key -> remaining credits

const DOMAINS = [
	'runnersworld.com', 'nike.com', 'adidas.com', 'rei.com', 'amazon.com',
	'wikipedia.org', 'reddit.com', 'youtube.com', 'medium.com', 'outdoorgearlab.com',
	'newbalance.com', 'asics.com', 'brooksrunning.com', 'dickssportinggoods.com',
	'walmart.com', 'etsy.com', 'quora.com', 'pinterest.com', 'instagram.com', 'shopify.com',
];

function fnv1a(str) {
	let h = 0x811c9dc5;
	for (let i = 0; i < str.length; i++) {
		h ^= str.charCodeAt(i);
		h = Math.imul(h, 0x01000193) >>> 0;
	}
	return h >>> 0;
}

function titleCase(s) {
	return s.replace(/\b\w/g, (c) => c.toUpperCase());
}

function slug(s) {
	return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

// "true" rank within the top 100, plus a chronic-not-found flag.
function trueRank(url, keyword, gl, day) {
	const seed = fnv1a((url + '|' + keyword + '|' + gl).toLowerCase());
	const base = (seed % 28) + 2; // 2..29 baseline
	const h = fnv1a(seed + ':' + day);
	const wobble = (h % 9) - 4; // -4..+4
	const rank = Math.max(1, Math.min(100, base + wobble));
	let found = true;
	if (seed % 23 === 0) found = false;
	else if (base >= 27 && h % 5 === 0) found = false;
	return { rank, found };
}

function organic(url, keyword, gl, day, pages, placedRank) {
	const total = pages * 10;
	const out = [];
	for (let pos = 1; pos <= total; pos++) {
		if (placedRank && pos === placedRank) {
			out.push({
				position: pos,
				title: titleCase(keyword),
				link: url,
				snippet: 'Your tracked page — ' + titleCase(keyword) + '. ' + url,
			});
			continue;
		}
		const ds = fnv1a(keyword + '|' + pos + '|' + day + '|' + gl);
		const domain = DOMAINS[ds % DOMAINS.length];
		const brand = domain.split('.')[0];
		out.push({
			position: pos,
			title: titleCase(keyword) + ' | ' + titleCase(brand),
			link: 'https://' + domain + '/' + slug(keyword) + '-' + pos,
			snippet: 'Discover ' + keyword + ' — reviews, guides and prices on ' + domain + '.',
			displayedLink: domain + ' › ' + slug(keyword),
		});
	}
	return out;
}

function relatedSearches(keyword) {
	const mods = ['best', 'cheap', 'reviews', 'near me', '2026', 'vs'];
	return mods.map((m) => ({
		query: m === 'vs' ? keyword + ' vs alternatives' : m + ' ' + keyword,
		link: 'https://www.google.com/search?q=' + encodeURIComponent(m + ' ' + keyword),
	}));
}

function peopleAlsoAsk(keyword) {
	return [
		'What is the best ' + keyword + '?',
		'How much does ' + keyword + ' cost?',
		'Is ' + keyword + ' worth it?',
	];
}

function rankResponse(body, apiKey) {
	const url = String(body.url || '');
	const keyword = String(body.q || body.query || '');
	const gl = String(body.gl || 'us');
	const matchType = body.match_type === 'exact' ? 'exact' : 'domain';
	const pages = Math.max(1, Math.min(10, parseInt(body.pages, 10) || 1));
	const isBackfill = body._day != null;
	const day = isBackfill ? parseInt(body._day, 10) : Math.floor(Date.now() / 86400000);

	const tr = trueRank(url, keyword, gl, day);
	const visibleRank = tr.found && tr.rank <= pages * 10 ? tr.rank : null; // within scanned depth
	const placed = visibleRank;
	const org = organic(url, keyword, gl, day, pages, placed);

	const cost = 6 * pages; // per-page (matches the real per-page model)
	const cur = balances[apiKey] == null ? 1000000 : balances[apiKey];
	const balance = isBackfill ? cur : Math.max(0, cur - cost);
	if (!isBackfill) balances[apiKey] = balance;

	return {
		url,
		search: keyword,
		gl,
		match_type: matchType,
		pages_scanned: pages,
		found: visibleRank !== null,
		rank: visibleRank,
		matches: visibleRank !== null
			? [{
				rank: visibleRank,
				page: Math.ceil(visibleRank / 10),
				position_on_page: ((visibleRank - 1) % 10) + 1,
				link: url,
				title: titleCase(keyword),
			}]
			: [],
		organic: org,
		people_also_ask: peopleAlsoAsk(keyword),
		related_searches: relatedSearches(keyword),
		partial: false,
		pages_failed: [],
		stats: {
			balance,
			cost: isBackfill ? 0 : cost,
			pages_cached: 0,
			pages_fresh: pages,
		},
	};
}

const server = http.createServer((req, res) => {
	const send = (code, obj) => {
		const json = JSON.stringify(obj);
		res.writeHead(code, { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(json) });
		res.end(json);
	};

	if (req.method === 'GET' && req.url === '/health') {
		return send(200, { ok: true, service: 'serpcheap-mock-api' });
	}

	if (req.method === 'POST' && (req.url === '/v1/rank' || req.url === '/v1/search')) {
		const apiKey = req.headers['x-api-key'];
		if (!apiKey) {
			return send(401, { error: 'missing_api_key', message: 'An API key is required.' });
		}
		let raw = '';
		req.on('data', (c) => { raw += c; if (raw.length > 1e6) req.destroy(); });
		req.on('end', () => {
			let body = {};
			try { body = raw ? JSON.parse(raw) : {}; } catch (e) { return send(400, { error: 'invalid_request', message: 'Bad JSON.' }); }
			const out = rankResponse(body, apiKey);
			// eslint-disable-next-line no-console
			console.log(`[mock-api] ${req.url} key=${String(apiKey).slice(0, 10)} "${out.search}" gl=${out.gl} p=${out.pages_scanned} -> ${out.found ? '#' + out.rank : 'not found'} (bal ${out.stats.balance})`);
			send(200, out);
		});
		return;
	}

	send(404, { error: 'not_found', message: 'No route.' });
});

server.listen(PORT, () => {
	// eslint-disable-next-line no-console
	console.log(`serpcheap mock API listening on :${PORT}`);
});
