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
This is the most basic way of creating the API:
```php
$api = new OpenFoodFacts\Api('food','fr');
$prd = $api->getProduct('3057640385148');
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


##### Optional Parameters
The other parameters are optional and for a more sophisticated use of the api (from a software development point of view):

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
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request
