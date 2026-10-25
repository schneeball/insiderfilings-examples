# InsiderFilings API Examples

Runnable examples for working with normalized SEC insider
transactions from Forms 3, 4 and 5.

- Website: https://insiderfilings.info
- Interactive playground: https://playground.insiderfilings.info
- API documentation: https://insiderfilings.info/api/v1/docs/
- Free 7-day trial: https://insiderfilings.info/api/v1/trial/

## Try it in your browser

The playground lets you compose requests, inspect responses,
and copy cURL, JavaScript and Python snippets.

No API key yet? [Get a free trial key](https://insiderfilings.info/api/v1/trial/)
for live API data, or use the playground's sample mode with clearly fictional data.

## Quick start

Set your API key as an environment variable:

```bash
export INSIDERFILINGS_API_KEY="YOUR_API_KEY"
```

In Windows PowerShell:

```powershell
$env:INSIDERFILINGS_API_KEY = "YOUR_API_KEY"
```

Fetch 25 transaction line items with Bash/cURL:

```bash
curl --fail-with-body --silent --show-error \
  -H "X-API-Key: $INSIDERFILINGS_API_KEY" \
  "https://insiderfilings.info/api/v1/transactions?limit=25"
```

Never commit your API key to this repository.

## Example workflows

The playground includes recipes for:

- Open-market purchases
- Sales of $1 million or more
- Director buying
- CEO and CFO activity
- Amended filings
- Price-quality flags

Use the playground to configure and test a request before
using it in a script.

## Data notes

- Decimal values are returned as strings.
- A null value does not mean zero.
- API results are transaction line items, not a count of
  unique filings or people.
- SEC filings remain the authoritative source.

## Trial access

At the time of writing, trial keys allow:

- 100 requests per UTC day
- 10 requests per minute
- Access to filings accepted within the last 30 days

[Get a free trial key](https://insiderfilings.info/api/v1/trial/).
See the [API documentation](https://insiderfilings.info/api/v1/docs/)
for current access conditions, limits and available filters.

## Runnable examples

Run these examples from the repository root, with `INSIDERFILINGS_API_KEY` set.

| Language | File | What it downloads |
| --- | --- | --- |
| Bash/cURL | [download_transactions.sh](examples/download_transactions.sh) | One response page |
| Python | [download_transactions.py](examples/download_transactions.py) | Multiple pages with cursor pagination |
| PHP | [download_transactions.php](examples/download_transactions.php) | Multiple pages with cursor pagination |
| JavaScript/Node.js | [download_transactions.mjs](examples/download_transactions.mjs) | Multiple pages with cursor pagination |

### Download one page with cURL

On macOS, Linux or in Git Bash:

```bash
bash examples/download_transactions.sh
```

This saves one response page to `transactions.json`.

### Download multiple pages with Python

Requires Python 3.10 or later. The example uses only the Python standard
library; no packages need to be installed. Set `INSIDERFILINGS_API_KEY`
as shown above, then run this command from the repository root:

```bash
python3 examples/download_transactions.py
```

On Windows, use `py -3` in place of `python3`.

For reported purchases from two tickers:

```bash
python3 examples/download_transactions.py --ticker AAPL,MSFT --transaction-code P --max-pages 5
```

### Download multiple pages with PHP

Requires PHP 8.3 or later with the cURL extension enabled for the CLI.
No Composer packages need to be installed. Run it from the command line
or a cron job:

```bash
php examples/download_transactions.php
```

For reported purchases from two tickers:

```bash
php examples/download_transactions.php --ticker AAPL,MSFT --transaction-code P --max-pages 5
```

### Download multiple pages with JavaScript (Node.js)

Requires Node.js 22 or later. The example uses built-in `fetch` and runs
in Node.js; no npm packages need to be installed.

```bash
node examples/download_transactions.mjs
```

For reported purchases from two tickers:

```bash
node examples/download_transactions.mjs --ticker AAPL,MSFT --transaction-code P --max-pages 5
```

### Pagination options and output

The Python, PHP and Node.js examples use the same options and export format:

| Option | Meaning | Default |
| --- | --- | --- |
| `--limit` | Transaction line items per page, 1-100 | `100` |
| `--max-pages` | Maximum number of pages to fetch | `3` |
| `--ticker` | Comma-separated issuer tickers | No ticker filter |
| `--transaction-code` | Comma-separated SEC transaction codes, such as `P` or `P,S` | No code filter |
| `--output` | Export path | `transactions_paginated.json` |
| `--help` | Show usage and options | |

By default, each example fetches up to three pages of 100 transaction line
items each and saves the combined results to `transactions_paginated.json`.
The output contains a `data` array and `download` metadata with
`pages_fetched` and `has_more`. A true `download.has_more` means the page
cap was reached and more results are available.

Each script reads `pagination.next_cursor` from each response. Filters and
`limit` are sent on the first request only; subsequent requests send only
`cursor`, which already carries the original filters, page size and snapshot.
It stops when `pagination.has_more` is false or `--max-pages` is reached.
Decimal strings and `null` values are preserved.

Every page consumes one API request. Each script pauses 6.1 seconds between
pages to pace requests for trial access. If the API returns HTTP 429, it stops
and reports `Retry-After` when available. Other HTTP or network errors also
stop the download. The output file is written only after all requested pages
have been fetched successfully; an existing file is overwritten on success.

Use `--help` with any of the three paginated examples to see its options.

## Disclaimer

For information and research only. Not investment advice.
