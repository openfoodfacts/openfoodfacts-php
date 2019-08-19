# openfoodfacts-php
![Open Food Facts](https://static.openfoodfacts.org/images/misc/openfoodfacts-logo-en-178x150.png)

PHP API Wrapper for [Open Food Facts](https://openfoodfacts.org/), the open database about food.

[![Project Status](http://opensource.box.com/badges/active.svg)](http://opensource.box.com/badges)
[![Build Status](https://travis-ci.org/openfoodfacts/openfoodfacts-php.svg?branch=master)](https://travis-ci.org/openfoodfacts/openfoodfacts-php)
[![Average time to resolve an issue](https://isitmaintained.com/badge/resolution/openfoodfacts/openfoodfacts-php.svg)](https://isitmaintained.com/project/openfoodfacts/openfoodfacts-php "Average time to resolve an issue")
[![Percentage of issues still open](https://isitmaintained.com/badge/open/openfoodfacts/openfoodfacts-php.svg)](https://isitmaintained.com/project/openfoodfacts/openfoodfacts-php "Percentage of issues still open")

## Installation

With Composer:

```bash
composer require openfoodfacts/openfoodfacts-php
```

## Usage
Is possible using API without Wrapper.
```php
$api = new OpenFoodFacts\Api('food','fr-en',$log);
$prd = $api->getProduct('3057640385148');
```

## Development


## Contributing

1. Fork it ( https://github.com/openfoodfacts/openfoodfacts-php/fork )
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request
