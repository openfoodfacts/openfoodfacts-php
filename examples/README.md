# Examples

🇫🇷 [Version française](README.fr.md) · 🇬🇧 English

| Example | What it shows |
|---|---|
| [`00_flat_request`](00_flat_request) | Reading a product over raw HTTP, **without the SDK**: v3.6 URL, `User-Agent` header, barcode validation, v3 response envelope and HTML escaping. Read it to see what the wrapper handles for you. |
| [`01-basic_api_usage`](01-basic_api_usage) | The same read through `OpenFoodFacts\Api`, with a PSR-3 logger, an HTTP client and a PSR-16 cache injected in the constructor. |

## Running the examples

`00_flat_request` has no dependencies:

```sh
php -S 127.0.0.1:8000 -t examples/00_flat_request
# then open http://127.0.0.1:8000/index.html
```

`01-basic_api_usage` uses the repository autoloader (`composer install` at the root):

```sh
php examples/01-basic_api_usage/cached_example.php
```
