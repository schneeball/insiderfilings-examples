#!/usr/bin/env bash
set -euo pipefail

: "${INSIDERFILINGS_API_KEY:?Set INSIDERFILINGS_API_KEY first}"

curl --fail-with-body --silent --show-error \
  -H "X-API-Key: ${INSIDERFILINGS_API_KEY}" \
  "https://insiderfilings.info/api/v1/transactions?limit=25" \
  --output transactions.json

printf 'Saved API response to transactions.json\n'
