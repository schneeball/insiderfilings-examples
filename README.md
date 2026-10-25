# InsiderFilings API Examples

Runnable examples for working with normalized SEC insider
transactions from Forms 3, 4 and 5.

- Website: https://insiderfilings.info
- Interactive playground: https://playground.insiderfilings.info

## Try it in your browser

The playground lets you compose requests, inspect responses,
and copy cURL, JavaScript and Python snippets.

No API key yet? Sample mode uses clearly fictional data.
A key is required to request live API data.

## Quick start

Set your API key as an environment variable:

```bash
export INSIDERFILINGS_API_KEY="YOUR_API_KEY"
```

Fetch 25 transaction line items:

```bash
curl --fail-with-body --silent --show-error \
  -H "X-API-Key: $INSIDERFILINGS_API_KEY" \
  "[https://insiderfilings.info/api/v1/transactions?limit=25](https://insiderfilings.info/api/v1/transactions?limit=25)"
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

Check the playground for current access conditions and limits.

## Download an API response

On macOS, Linux or in Git Bash:

```bash
bash examples/download_transactions.sh
```

This saves one response page to `transactions.json`.

## Disclaimer

For information and research only. Not investment advice.
