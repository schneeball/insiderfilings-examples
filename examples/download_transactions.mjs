#!/usr/bin/env node
// CLI example: Node.js 22+ with built-in fetch. No npm packages required.
// Documentation: https://insiderfilings.info/api/v1/docs/
import { writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { setTimeout as sleep } from 'node:timers/promises';

const API_URL = 'https://insiderfilings.info/api/v1/transactions';
const PAGE_DELAY_MS = 6100;

export async function fetchPage(apiKey, params, apiUrl = API_URL) {
  const url = new URL(apiUrl);
  url.search = new URLSearchParams(params).toString();
  const response = await fetch(url, {
    headers: { 'X-API-Key': apiKey, Accept: 'application/json' },
    signal: AbortSignal.timeout(30_000),
    redirect: 'error',
  });
  if (response.status !== 200) await response.body?.cancel();
  if (response.status === 429) {
    const retryAfter = response.headers.get('Retry-After');
    const hint = retryAfter ? ` Retry after ${retryAfter} seconds.` : '';
    throw new Error(`HTTP 429: API quota or rate limit reached.${hint}`);
  }
  if (response.status === 401) {
    throw new Error('HTTP 401: check your API key and its expiry.');
  }
  if (response.status !== 200) {
    throw new Error(`API request failed with HTTP ${response.status}.`);
  }
  const page = await response.json();
  if (!page || !Array.isArray(page.data)) {
    throw new Error('Unexpected response: expected a data array.');
  }
  const pagination = page.pagination;
  if (!pagination || typeof pagination.has_more !== 'boolean') {
    throw new Error('Unexpected response: missing pagination.has_more.');
  }
  if (pagination.has_more &&
      (typeof pagination.next_cursor !== 'string' || pagination.next_cursor.length === 0)) {
    throw new Error('Unexpected response: has_more is true without a next_cursor.');
  }
  return page;
}

export async function downloadTransactions(requestPage, params, maxPages, pause = sleep) {
  const rows = [];
  const seenCursors = new Set();
  let pagesFetched = 0;
  let hasMore = false;
  for (let pageNumber = 1; pageNumber <= maxPages; pageNumber++) {
    const page = await requestPage(params);
    rows.push(...page.data);
    pagesFetched = pageNumber;
    hasMore = page.pagination.has_more;
    console.error(`Page ${pageNumber}: ${page.data.length} rows`);
    if (!hasMore) break;

    const cursor = page.pagination.next_cursor;
    if (seenCursors.has(cursor)) {
      throw new Error('The API returned a repeated cursor; stopping the download.');
    }
    seenCursors.add(cursor);
    // The cursor carries the filters and limit. Do not send them again.
    params = { cursor };
    if (pageNumber < maxPages) await pause(PAGE_DELAY_MS);
  }
  return { data: rows, download: { pages_fetched: pagesFetched, has_more: hasMore } };
}

export async function main(args = process.argv.slice(2)) {
  try {
    const { values } = parseArgs({ args, options: {
      limit: { type: 'string', default: '100' },
      'max-pages': { type: 'string', default: '3' },
      ticker: { type: 'string' },
      'transaction-code': { type: 'string' },
      output: { type: 'string', default: 'transactions_paginated.json' },
      help: { type: 'boolean', short: 'h' },
    } });
    if (values.help) {
      console.log('Usage: node examples/download_transactions.mjs [options]\n\n' +
        'Set INSIDERFILINGS_API_KEY before running this example.\n' +
        '  --limit N                 Rows per page, 1-100 (default: 100)\n' +
        '  --max-pages N             Maximum pages (default: 3)\n' +
        '  --ticker AAPL,MSFT        Comma-separated issuer tickers\n' +
        '  --transaction-code P      SEC transaction codes, e.g. P or P,S\n' +
        '  --output FILE             Export path (default: transactions_paginated.json)\n' +
        '  --help                    Show this help');
      return 0;
    }
    const limit = Number(values.limit);
    const maxPages = Number(values['max-pages']);
    if (!/^\d+$/.test(values.limit) || !Number.isSafeInteger(limit) || limit < 1 || limit > 100 ||
        !/^\d+$/.test(values['max-pages']) || !Number.isSafeInteger(maxPages) || maxPages < 1) {
      throw new Error('--limit must be 1-100 and --max-pages must be a positive integer.');
    }
    if (!values.output) throw new Error('--output must not be empty.');
    const apiKey = process.env.INSIDERFILINGS_API_KEY?.trim();
    if (!apiKey) throw new Error('Set INSIDERFILINGS_API_KEY first.');
    const params = { limit };
    if (values.ticker) params.ticker = values.ticker;
    if (values['transaction-code']) params.transaction_code = values['transaction-code'];

    const result = await downloadTransactions(query => fetchPage(apiKey, query), params, maxPages);
    await writeFile(values.output, JSON.stringify(result, null, 2) + '\n', 'utf8');
    console.log(`Saved ${result.data.length} transaction line items to ${values.output}`);
    if (result.download.has_more) {
      console.log('Page cap reached; more results are available. Increase --max-pages to fetch more.');
    }
    return 0;
  } catch (error) {
    console.error(`Error: ${error.message}`);
    return 1;
  }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  process.exitCode = await main();
}
