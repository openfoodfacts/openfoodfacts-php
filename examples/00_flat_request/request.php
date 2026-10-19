<?php
declare(strict_types=1);

/**
 * Flat request: reading a product straight over HTTP, without the SDK.
 *
 * This example exists to show what the wrapper does for you. Even this minimal version has to
 * identify itself, validate the barcode, check the HTTP status, read the v3 response envelope
 * and escape everything before printing it. `OpenFoodFacts\Api` handles all of it, plus caching,
 * exceptions and the write endpoints — see ../01-basic_api_usage.
 *
 * Usage: serve this folder and open index.html, or run
 *   php -S 127.0.0.1:8000 && open http://127.0.0.1:8000/index.html
 */

// Open Food Facts requires every client to identify itself: put your app and a contact point
// here. Anonymous traffic can be rate-limited or blocked.
const USER_AGENT = 'OFF flat example - Web - 1.0 - https://example.org';

// Read API v3.6. `fields` keeps the response small and `lc`
// localises names and tags.
const ENDPOINT = 'https://world.openfoodfacts.org/api/v3.6/product/%s.json?fields=%s&lc=%s';

/** Print an escaped one-line error page and stop. */
function fail(string $message, int $status = 400): never {
    http_response_code($status);
    echo '<!doctype html><meta charset="utf-8"><p>', htmlspecialchars($message, ENT_QUOTES), '</p>';
    echo '<p><a href="index.html">Try another barcode</a></p>';
    exit;
}

// A barcode is a string of digits: casting it to int would silently drop leading zeros, which
// plenty of EAN-8 and UPC-A codes have.
$barcode = trim((string)($_GET['ean13'] ?? ''));
if (!preg_match('/^[0-9]{4,24}$/D', $barcode)) {
    fail('Enter a barcode made of 4 to 24 digits.');
}

$url = sprintf(ENDPOINT, rawurlencode($barcode), rawurlencode('product_name,brands,image_small_url'), 'fr');

$context = stream_context_create(['http' => [
    'method'        => 'GET',
    'header'        => 'User-Agent: ' . USER_AGENT . "\r\nAccept: application/json\r\n",
    'timeout'       => 15,
    // Without this, a 404 makes file_get_contents() return false instead of the JSON body that
    // explains what went wrong.
    'ignore_errors' => true,
]]);

$result = @file_get_contents($url, false, $context);
if ($result === false) {
    fail('Open Food Facts is unreachable. Try again in a moment.', 502);
}

// $http_response_header is populated by the HTTP stream wrapper.
$statusLine = $http_response_header[0] ?? '';
$httpStatus = (int)(preg_match('#\s(\d{3})\s#', $statusLine, $m) ? $m[1] : 0);

try {
    $json = json_decode($result, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    // Handle an HTML error page instead of JSON without triggering a TypeError.
    fail('Open Food Facts did not return JSON.', 502);
}

// v3 response envelope: status is "success" or "failure", and a missing product is a 404.
if ($httpStatus === 404 || ($json['status'] ?? '') !== 'success') {
    $reason = $json['errors'][0]['message']['name'] ?? 'No product found for this barcode.';
    fail($reason, $httpStatus ?: 502);
}

$product = $json['product'] ?? [];

// Product data is contributed by users: never interpolate it into HTML unescaped.
$escape = static fn (mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES);

echo str_replace(
    ['{productName}', '{brand}', '{image}', '{json}'],
    [
        $escape($product['product_name'] ?? 'Unnamed product'),
        $escape($product['brands'] ?? '—'),
        $escape($product['image_small_url'] ?? ''),
        $escape(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    ],
    file_get_contents(__DIR__ . '/response.html')
);
