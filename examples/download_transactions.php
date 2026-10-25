<?php
declare(strict_types=1);

// CLI example: PHP 8.3+ with the cURL extension. No Composer packages required.
// Documentation: https://insiderfilings.info/api/v1/docs/
const API_URL = 'https://insiderfilings.info/api/v1/transactions';

function fetchPage(string $apiKey, array $params, string $apiUrl = API_URL): stdClass
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('Enable the PHP cURL extension first.');
    }
    $url = $apiUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }
    $retryAfter = null;
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$retryAfter): int {
            if (stripos($line, 'Retry-After:') === 0) {
                $retryAfter = trim(substr($line, strlen('Retry-After:')));
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException('Network request failed: ' . curl_error($handle));
    }
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if ($status === 429) {
        $hint = $retryAfter !== null ? " Retry after {$retryAfter} seconds." : '';
        throw new RuntimeException('HTTP 429: API quota or rate limit reached.' . $hint);
    }
    if ($status === 401) {
        throw new RuntimeException('HTTP 401: check your API key and its expiry.');
    }
    if ($status !== 200) {
        throw new RuntimeException("API request failed with HTTP {$status}.");
    }

    // Decode objects as objects so nested empty objects remain {} when exported.
    $page = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
    if (!$page instanceof stdClass || !is_array($page->data ?? null)) {
        throw new RuntimeException('Unexpected response: expected a data array.');
    }
    $pagination = $page->pagination ?? null;
    if (!$pagination instanceof stdClass || !is_bool($pagination->has_more ?? null)) {
        throw new RuntimeException('Unexpected response: missing pagination.has_more.');
    }
    if ($pagination->has_more &&
        (!is_string($pagination->next_cursor ?? null) || $pagination->next_cursor === '')) {
        throw new RuntimeException('Unexpected response: has_more is true without a next_cursor.');
    }
    return $page;
}

function downloadTransactions(callable $requestPage, array $params, int $maxPages, ?callable $pause = null): array
{
    $pause ??= static function (): void {
        sleep(6);
        usleep(100000);
    };
    $rows = [];
    $seenCursors = [];
    $pagesFetched = 0;
    $hasMore = false;
    for ($pageNumber = 1; $pageNumber <= $maxPages; $pageNumber++) {
        $page = $requestPage($params);
        foreach ($page->data as $row) {
            $rows[] = $row;
        }
        $pagesFetched = $pageNumber;
        $hasMore = $page->pagination->has_more;
        fwrite(STDERR, "Page {$pageNumber}: " . count($page->data) . " rows\n");
        if (!$hasMore) {
            break;
        }

        $cursor = $page->pagination->next_cursor;
        if (isset($seenCursors[$cursor])) {
            throw new RuntimeException('The API returned a repeated cursor; stopping the download.');
        }
        $seenCursors[$cursor] = true;
        // The cursor carries the filters and limit. Do not send them again.
        $params = ['cursor' => $cursor];
        if ($pageNumber < $maxPages) {
            $pause();
        }
    }
    return [
        'data' => $rows,
        'download' => ['pages_fetched' => $pagesFetched, 'has_more' => $hasMore],
    ];
}

function parseOptions(array $argv): array
{
    $options = [
        'limit' => '100', 'max-pages' => '3', 'ticker' => null,
        'transaction-code' => null, 'output' => 'transactions_paginated.json',
    ];
    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--help' || $argv[$i] === '-h') {
            return ['help' => true];
        }
        [$name, $value] = array_pad(explode('=', $argv[$i], 2), 2, null);
        if (!str_starts_with($name, '--') || !array_key_exists(substr($name, 2), $options)) {
            throw new InvalidArgumentException('Unknown argument: ' . $name);
        }
        if ($value === null) {
            if (!isset($argv[$i + 1]) || str_starts_with($argv[$i + 1], '--')) {
                throw new InvalidArgumentException('Missing value for ' . $name);
            }
            $value = $argv[++$i];
        }
        if ($value === '') {
            throw new InvalidArgumentException('Empty value for ' . $name);
        }
        $options[substr($name, 2)] = $value;
    }
    return $options;
}

function main(array $argv): int
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(400);
        echo "Run this example from the command line.\n";
        return 1;
    }
    try {
        $options = parseOptions($argv);
        if (isset($options['help'])) {
            echo "Usage: php examples/download_transactions.php [options]\n\n"
                . "Set INSIDERFILINGS_API_KEY before running this example.\n"
                . "  --limit N                 Rows per page, 1-100 (default: 100)\n"
                . "  --max-pages N             Maximum pages (default: 3)\n"
                . "  --ticker AAPL,MSFT        Comma-separated issuer tickers\n"
                . "  --transaction-code P      SEC transaction codes, e.g. P or P,S\n"
                . "  --output FILE             Export path (default: transactions_paginated.json)\n"
                . "  --help                    Show this help\n";
            return 0;
        }
        $limit = filter_var($options['limit'], FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $maxPages = filter_var($options['max-pages'], FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if ($limit === false || $maxPages === false) {
            throw new InvalidArgumentException('--limit must be 1-100 and --max-pages must be a positive integer.');
        }
        $apiKey = trim(getenv('INSIDERFILINGS_API_KEY') ?: '');
        if ($apiKey === '') {
            throw new InvalidArgumentException('Set INSIDERFILINGS_API_KEY first.');
        }
        $params = ['limit' => $limit];
        foreach (['ticker' => 'ticker', 'transaction-code' => 'transaction_code'] as $option => $parameter) {
            if ($options[$option] !== null) {
                $params[$parameter] = $options[$option];
            }
        }
        $result = downloadTransactions(fn(array $query) => fetchPage($apiKey, $query), $params, $maxPages);
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($options['output'], $json) === false) {
            throw new RuntimeException('Could not write the output file.');
        }
        echo 'Saved ' . count($result['data']) . ' transaction line items to ' . $options['output'] . "\n";
        if ($result['download']['has_more']) {
            echo "Page cap reached; more results are available. Increase --max-pages to fetch more.\n";
        }
        return 0;
    } catch (Throwable $error) {
        fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main($_SERVER['argv'] ?? []));
}
