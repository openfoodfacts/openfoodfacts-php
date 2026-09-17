# openfoodfacts-php — Official PHP package for Open Food Facts

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-dark.png?refresh_github_cache=1">
  <source media="(prefers-color-scheme: light)" srcset="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-light.png?refresh_github_cache=1">
  <img height="48" src="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-light.svg" alt="Open Food Facts">
</picture>

🇫🇷 [Version française](README.fr.md) · 🇬🇧 English

Official PHP wrapper for [Open Food Facts](https://openfoodfacts.org/), the open database about food products.

The development branch migrates product reads, writes and image uploads to the **Open Food Facts API v3.6** (product schema 1004) and fixes several robustness bugs present in v0.4.0, which used the legacy v0 API.

📖 **Full documentation: [doc/home.md](doc/home.md)** — this README covers the migration from v0.4.0 and the main SDK changes.

The v3 migration covers products and their images. `Api::search()` still uses `cgi/search.pl`; `getByFacets()` and facet accessors use the website’s `.json` URLs. There is no v3 search endpoint: see the [official API feature matrix](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/). The separate `SearchApi` client exposes Search-a-licious.

## Installation

With Composer:

```bash
composer require openfoodfacts/openfoodfacts-php
```

This documentation describes the development branch's migration to API v3.6. The command above installs the latest stable release published on Packagist, which may not yet include these changes. The package name remains `openfoodfacts/openfoodfacts-php` and PHP namespaces remain `OpenFoodFacts\`.

## Quick start

```php
// The user agent (1st argument) is mandatory — describe your application
$api = new OpenFoodFacts\Api('MyApp - Web - 1.0 - https://example.org', 'food', 'fr');

// Read through API v3.6: filter the fields (recommended) and localize the response
$product = $api->getProduct('3057640385148', ['product_name', 'nutriscore_grade'], 'fr');
echo $product->product_name;

// Structured write (v3 PATCH) and image upload (v3 POST) — credentials required
$api->authentification('user', 'password');
$api->updateProduct('3057640385148', ['product_name_fr' => 'Eau de Volvic', 'categories_tags' => ['en:waters']]);
$api->uploadImage('3057640385148', 'front', '/path/to/image.jpg', 'fr');
```

## Migrating from v0.4.0

### 1. Migration to API v3.6

Ref: [API and product schema change log](https://openfoodfacts.github.io/openfoodfacts-server/api/ref-api-and-product-schema-change-log/)

| Feature | v0.4.0 | Development branch (API v3.6) |
|---|---|---|
| Product read | `GET /api/v0/product/{code}` | `GET /api/v3.6/product/{code}` (product schema 1004 pinned) |
| Read parameters | none | `fields`, `lc`, `cc`, `tags_lc` (optional, backward compatible) |
| Product write | `cgi/product_jqm2.pl` (legacy form) | new `updateProduct()` method: `PATCH /api/v3.6/product/{code}` with structured JSON (language-specific fields, tags, packagings, image selection) |
| Image upload | `cgi/product_image_upload.pl` (multipart, food only) | `POST /api/v3.6/product/{code}/images` (base64 + `selected` structure per field/language, all flavors) |
| Response envelope | `status` 0/1 | v3 envelope (`status`, `result`, `errors[]`, `warnings[]`) with readable error messages extracted from `errors[]` |
| Partially rejected write | not detectable | `success_with_errors` → `ProductUpdateException` (the full envelope stays available through `getResponse()`); `success_with_warnings` logged through the logger |
| Product of another type (cross-flavor) | not handled | `product_type` parameter of `getProduct()` (e.g. `'all'`): the server redirects to the right flavor, and the returned document matches that flavor (e.g. `BeautyDocument`) |
| Redirects | `strict = false` (a PATCH/POST redirected with 301/302 would be downgraded to a body-less GET) | `strict` mode: method and body are preserved across redirects |

Implementation details:

- `Api::API_VERSION = '3.6'` (public constant): every product route is versioned, so the response structure stays stable even as the server schema evolves.
- `addNewProduct()` (legacy cgi) is kept but **deprecated** in favor of `updateProduct()`.
- `Document::__get` returns `null` for a missing field (instead of a PHP warning): essential with v3.6, which removes the `*_hierarchy` and `*_lc` fields in favor of `tags_sources`, and since v3.1 renames `ecoscore_*` to `environmental_score_*`.

### 2. Fixes for bugs present in v0.4.0

- **POST responses are no longer cached**: in v0.4.0, a second identical write (`addNewProduct`, `uploadImage`) within the cache lifetime never hit the network — a silently lost write. On top of that, the cache key collided for every upload (resource not serializable by `json_encode`).
- **TTL on the read cache (1 h)**: v0.4.0 cached product data with no expiration.
- **Strict JSON decoding** (`JSON_THROW_ON_ERROR`) and **HTTP status checks**: an HTML error page from the server caused a `TypeError` in v0.4.0.
- **Barcode validation** (digits only) and URL encoding: no more URL segment injection through `getProduct()`.
- `404` → `ProductNotFoundException`, missing credentials → `MissingCredentialsException`, invalid barcode → `InvalidBarcodeException` (extends `InvalidParameterException`, rejected value available through `getBarcode()`).
- **`activeTestMode()` separates the staging htaccess from the contributor credentials**: `off`/`off` is the htaccess (HTTP Basic) gate of the `.net` test server, not a contributor account. The SDK sends it in the `Authorization` header, while the contributor credentials — provided through `authentification()` — go into the body of v3 writes (`user_id`/`password`). Version v0.4.0 conflated the two: `activeTestMode()` overwrote the contributor credentials with `off`/`off`, making every staging write fail.

### 3. Fixed documentation and examples

- The v0.4.0 README examples no longer compiled (the constructor has required `$userAgent` as first argument since 0.4.0); `cached_example.php` also passed an `int` to `getProduct(string)`.
- The dead Travis CI badge was removed.

### 4. Tests

- New unit suite `tests/Unit/OpenFoodFacts/ApiV3Test.php` (Guzzle `MockHandler`): versioned URL, v3 envelope, 404, readable errors, PATCH body, base64 payload — with no dependency on the live API.
- Integration tests brought in line (the "food-only upload" restriction no longer exists in v3).

## Compatibility

- PHP **8.1 → 8.5** (no syntax beyond 8.1; notably tested on 8.3).
- Unchanged runtime dependencies: Guzzle 7, PSR-3, PSR-16.
- Public API backward compatible, with the following changes: `uploadImage()` limits files to 10 MiB before encoding, now returns the v3 envelope and works for all flavors; non-numeric barcodes are rejected; `activeTestMode()` now only sets `off`/`off` as the `.net` htaccess — to write on staging, provide a contributor account through `authentification()`.
- The new exceptions (`InvalidParameterException`, `UnknownException`, `ProductUpdateException`) extend `BadRequestException`: a `catch (BadRequestException)` written against v0.4.0 keeps catching them.

## License and reuse

The code is licensed under [MIT](LICENSE).

- If you use this SDK, feel free to open a PR to document your application and its data reuse in this repository.
- Comply with the [OdBL license](https://opendatacommons.org/licenses/odbl/summary/) for Open Food Facts data: credit the source, avoid combining it with non-free data you cannot legally release as open data, and contribute back any products you add using this SDK.
- Get in touch at [reuse@openfoodfacts.org](mailto:reuse@openfoodfacts.org).
- You can also [tell the community about your reuse](https://forms.gle/hwaeqBfs8ywwhbTg8) and submit your application for a chance to be featured. This is optional.

## Authors

- [Roberto Moreno](https://rampmaster.org/) — Wrapper Developer.
- [Colin Benoit](https://github.com/Benoit382) — PHP Developer.
- The [project contributors](https://github.com/openfoodfacts/openfoodfacts-php/graphs/contributors) and the Open Food Facts community.
