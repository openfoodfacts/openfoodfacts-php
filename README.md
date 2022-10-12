# openfoodfacts-php - Official PHP package for Open Food Facts
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-dark.png?refresh_github_cache=1">
  <source media="(prefers-color-scheme: light)" srcset="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-light.png?refresh_github_cache=1">
  <img height="48" src="https://static.openfoodfacts.org/images/logos/off-logo-horizontal-light.svg">
</picture>

PHP API Wrapper for [Open Food Facts](https://openfoodfacts.org/), the open database about food.

[![Project Status](https://opensource.box.com/badges/active.svg)](https://opensource.box.com/badges)
[![Build Status](https://travis-ci.org/openfoodfacts/openfoodfacts-php.svg?branch=master)](https://travis-ci.org/openfoodfacts/openfoodfacts-php)
[![Average time to resolve an issue](https://isitmaintained.com/badge/resolution/openfoodfacts/openfoodfacts-php.svg)](https://isitmaintained.com/project/openfoodfacts/openfoodfacts-php "Average time to resolve an issue")
[![Percentage of issues still open](https://isitmaintained.com/badge/open/openfoodfacts/openfoodfacts-php.svg)](https://isitmaintained.com/project/openfoodfacts/openfoodfacts-php "Percentage of issues still open")

## Installation

With Composer:

```bash
composer require openfoodfacts/openfoodfacts-php
```

## Usage
This is the most basic way of creating the API:
```php
$api = new OpenFoodFacts\Api('food','fr');
$product = $api->getProduct('3057640385148');
```
In the example above you access the "food" database, limited to the French language/country scope. 
The first parameter is either 
 - "food"
 - "beauty" or 
 - "pet"
 
to decide which product database you want to use.

The second parameter decides the language/country scope of the chosen database: f.e. "world" or "de" or "fr". 

For more details on this topic: see the [API Documentation](https://en.wiki.openfoodfacts.org/API/Read#Countries_and_Language_of_the_Response)

These are all the parameters you really need for basic usage.

As return types for ```$api->getProduct``` you get an ```Document::class``` Object. 
This may also be an Object of Type  ```FoodProduct::class```,```PetProduct::class```, ```BeautyProduct::class``` depending on which API you are creating.
These objects inherit from the more generic ```Document::class```

In the example above, we use the 'food' API and there will get a ```FoodProduct::class```

For getting a first overview the ```Document::class``` has a function to return an array representation(sorted) for a first start. 
```php
$product = $api->getProduct('3057640385148');
$productDataAsArray = $product->getData();
```


#### Optional Parameters
The other parameters are optional and for a more sophisticated use of the api (from a software development point of view):

An example in code is found here: [cached_example.php](examples/01-basic_api_usage/cached_example.php)

LoggerInterface: A logger which decieds where to log errors to (file, console , etc) 

see: [PSR-3 Loggerinterface](https://www.php-fig.org/psr/psr-3/)

ClientInterface: The HTTP Client - to adjust the connection configs to your needs and more 

see: [Guzzle HTTP Client](https://packagist.org/packages/guzzlehttp/guzzle)

CacheInterface: To temporarily save the results of API request to improve the performance and to reduce the load on the API- Server 

see: [PSR-16 Simple Cache](https://www.php-fig.org/psr/psr-16/)

## Development

### Contributing

1. Fork it ( https://github.com/openfoodfacts/openfoodfacts-php/fork )
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Launch test `vendor/bin/phpunit` && cs-fixer `vendor/bin/php-cs-fixer fix`
4. Commit your changes (`git commit -am 'Add some feature'`)
5. Push to the branch (`git push origin my-new-feature`)
6. Create a new Pull Request

## Third party applications
If you use this SDK, feel free to open a PR to add your application in this list.

## Authors
