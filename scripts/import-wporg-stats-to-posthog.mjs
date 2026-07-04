#!/usr/bin/env node
/**
 * Import aggregate WordPress.org plugin stats into PostHog.
 *
 * This script is intended for an operator-run cron/CI job. Do not run it from
 * customer WordPress installs.
 */

const DEFAULT_SLUG = 'beepbeep-ai-alt-text-generator';
const DEFAULT_LIMIT = 30;
const DEFAULT_POSTHOG_HOST = 'https://us.i.posthog.com';

const args = new Map();
for (const rawArg of process.argv.slice(2)) {
	const [key, value] = rawArg.replace(/^--/, '').split('=');
	args.set(key, value === undefined ? true : value);
}

const slug = String(args.get('slug') || process.env.WPORG_SLUG || DEFAULT_SLUG);
const limit = Number.parseInt(String(args.get('limit') || process.env.WPORG_DOWNLOAD_DAYS || DEFAULT_LIMIT), 10);
const afterDate = String(args.get('after-date') || process.env.WPORG_DOWNLOAD_AFTER_DATE || '');
const dryRun = args.has('dry-run') || process.env.DRY_RUN === '1';
const posthogApiKey = String(process.env.POSTHOG_PROJECT_API_KEY || process.env.POSTHOG_API_KEY || '');
const posthogHost = String(process.env.POSTHOG_API_HOST || DEFAULT_POSTHOG_HOST).replace(/\/+$/, '');
const distinctId = `wporg:${slug}`;

function requireFetch() {
	if (typeof fetch !== 'function') {
		throw new Error('This script requires Node.js 18 or newer for global fetch().');
	}
}

async function fetchJson(url) {
	const response = await fetch(url, {
		headers: {
			'Accept': 'application/json',
			'User-Agent': 'BeepBeep AI WP.org stats importer',
		},
	});

	if (!response.ok) {
		throw new Error(`Request failed ${response.status} ${response.statusText}: ${url}`);
	}

	return response.json();
}

async function loadPluginInfo() {
	const url = new URL('https://api.wordpress.org/plugins/info/1.2/');
	url.searchParams.set('action', 'plugin_information');
	url.searchParams.set('request[slug]', slug);
	url.searchParams.set('request[fields][active_installs]', '1');
	url.searchParams.set('request[fields][downloaded]', '1');
	url.searchParams.set('request[fields][sections]', '0');
	url.searchParams.set('request[fields][versions]', '0');

	return fetchJson(url);
}

async function loadDownloadHistory() {
	const url = new URL('https://api.wordpress.org/stats/plugin/1.0/downloads.php');
	url.searchParams.set('slug', slug);
	url.searchParams.set('limit', String(Number.isFinite(limit) && limit > 0 ? limit : DEFAULT_LIMIT));

	return fetchJson(url);
}

function toInt(value) {
	const parsed = Number.parseInt(String(value || '0'), 10);
	return Number.isFinite(parsed) ? parsed : 0;
}

function buildEvents(pluginInfo, downloadsByDate) {
	const now = new Date().toISOString();
	const downloadedTotal = toInt(pluginInfo.downloaded);
	const activeInstalls = toInt(pluginInfo.active_installs);
	const pluginVersion = String(pluginInfo.version || '');
	const lastUpdated = String(pluginInfo.last_updated || '');
	const downloadRows = Object.entries(downloadsByDate || {})
		.filter(([date]) => !afterDate || date > afterDate)
		.sort(([a], [b]) => a.localeCompare(b))
		.map(([date, downloads]) => ({
			event: 'wporg_downloads_daily',
			distinct_id: distinctId,
			properties: {
				$insert_id: `wporg:${slug}:downloads:${date}`,
				slug,
				date,
				downloads: toInt(downloads),
				plugin_version: pluginVersion,
				source: 'wordpress_org',
				imported_at: now,
			},
		}));

	return [
		{
			event: 'wporg_stats_snapshot',
			distinct_id: distinctId,
			properties: {
				$insert_id: `wporg:${slug}:snapshot:${now.slice(0, 10)}`,
				slug,
				downloaded_total: downloadedTotal,
				active_installs: activeInstalls,
				plugin_version: pluginVersion,
				last_updated: lastUpdated,
				source: 'wordpress_org',
				imported_at: now,
			},
		},
		...downloadRows,
	];
}

async function sendEvent(event) {
	const response = await fetch(`${posthogHost}/capture/`, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'User-Agent': 'BeepBeep AI WP.org stats importer',
		},
		body: JSON.stringify({
			api_key: posthogApiKey,
			event: event.event,
			distinct_id: event.distinct_id,
			properties: event.properties,
		}),
	});

	if (!response.ok) {
		const body = await response.text();
		throw new Error(`PostHog capture failed ${response.status} ${response.statusText}: ${body.slice(0, 300)}`);
	}
}

async function main() {
	requireFetch();

	const [pluginInfo, downloadsByDate] = await Promise.all([
		loadPluginInfo(),
		loadDownloadHistory(),
	]);
	const events = buildEvents(pluginInfo, downloadsByDate);

	if (dryRun) {
		console.log(JSON.stringify({ dryRun: true, posthogHost, events }, null, 2));
		return;
	}

	if (!posthogApiKey) {
		throw new Error('Set POSTHOG_PROJECT_API_KEY or run with --dry-run.');
	}

	for (const event of events) {
		await sendEvent(event);
	}

	console.log(`Imported ${events.length} WordPress.org aggregate events for ${slug}.`);
}

main().catch((error) => {
	console.error(error.message);
	process.exit(1);
});
