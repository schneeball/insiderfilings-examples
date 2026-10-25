#!/usr/bin/env python3
"""Download a bounded number of InsiderFilings transaction pages.

Documentation: https://insiderfilings.info/api/v1/docs/
Set INSIDERFILINGS_API_KEY before running this example.
"""

import argparse
import json
import os
from pathlib import Path
import sys
import time
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


API_URL = "https://insiderfilings.info/api/v1/transactions"
PAGE_DELAY_SECONDS = 6.1


def fetch_page(api_key, params):
    request = Request(
        API_URL + "?" + urlencode(params),
        headers={"X-API-Key": api_key, "Accept": "application/json"},
    )
    try:
        with urlopen(request, timeout=30) as response:
            page = json.load(response)
    except HTTPError as error:
        if error.code == 429:
            retry_after = error.headers.get("Retry-After")
            hint = f" Retry after {retry_after} seconds." if retry_after else ""
            raise RuntimeError("HTTP 429: API quota or rate limit reached." + hint) from None
        if error.code == 401:
            raise RuntimeError("HTTP 401: check your API key and its expiry.") from None
        raise RuntimeError(f"API request failed with HTTP {error.code}.") from None

    if not isinstance(page, dict) or not isinstance(page.get("data"), list):
        raise RuntimeError("Unexpected response: expected a data array.")
    pagination = page.get("pagination")
    if not isinstance(pagination, dict) or not isinstance(pagination.get("has_more"), bool):
        raise RuntimeError("Unexpected response: missing pagination.has_more.")
    if pagination["has_more"]:
        cursor = pagination.get("next_cursor")
        if not isinstance(cursor, str) or not cursor:
            raise RuntimeError("Unexpected response: has_more is true without a next_cursor.")
    return page


def download_transactions(api_key, params, max_pages):
    rows = []
    seen_cursors = set()
    for page_number in range(1, max_pages + 1):
        page = fetch_page(api_key, params)
        rows.extend(page["data"])
        pagination = page["pagination"]
        has_more = pagination["has_more"]
        print(f"Page {page_number}: {len(page['data'])} rows", file=sys.stderr)
        if not has_more:
            break

        cursor = pagination["next_cursor"]
        if cursor in seen_cursors:
            raise RuntimeError("The API returned a repeated cursor; stopping the download.")
        seen_cursors.add(cursor)

        # The cursor carries the filters and limit. Do not send them again.
        params = {"cursor": cursor}
        if page_number < max_pages:
            time.sleep(PAGE_DELAY_SECONDS)

    return {
        "data": rows,
        "download": {"pages_fetched": page_number, "has_more": has_more},
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--limit", type=int, default=100, help="Rows per page, 1-100 (default: 100)")
    parser.add_argument("--max-pages", type=int, default=3, help="Maximum pages to fetch (default: 3)")
    parser.add_argument("--ticker", help="Comma-separated issuer tickers, e.g. AAPL,MSFT")
    parser.add_argument("--transaction-code", help="SEC transaction codes, e.g. P or P,S")
    parser.add_argument("--output", type=Path, default=Path("transactions_paginated.json"))
    args = parser.parse_args()
    if not 1 <= args.limit <= 100:
        parser.error("--limit must be between 1 and 100")
    if args.max_pages < 1:
        parser.error("--max-pages must be at least 1")
    api_key = os.environ.get("INSIDERFILINGS_API_KEY", "").strip()
    if not api_key:
        parser.error("Set INSIDERFILINGS_API_KEY first")

    params = {"limit": args.limit}
    if args.ticker:
        params["ticker"] = args.ticker
    if args.transaction_code:
        params["transaction_code"] = args.transaction_code

    try:
        result = download_transactions(api_key, params, args.max_pages)
        args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    except (OSError, RuntimeError, ValueError) as error:
        print(f"Error: {error}", file=sys.stderr)
        return 1

    print(f"Saved {len(result['data'])} transaction line items to {args.output}")
    if result["download"]["has_more"]:
        print("Page cap reached; more results are available. Increase --max-pages to fetch more.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
