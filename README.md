# openfoodfacts-php
PHP wrapper for Open Food Facts

## Installation

With Composer:

```bash
composer require openfoodfacts/php-client
```

## Usage
```php
// Where we will set the value of the scan
$scan = $_GET['ean13'];

// Connection to the API (french version here)
$url = file_get_contents('http://fr.openfoodfacts.org/api/v0/produit/'.$scan.'.json');

// Decoding the JSON into an usable array (the value "true" confirms that the return is only an array)
$json = json_decode($url, true);

// Test of display
//echo '<pre>' . print_r($json, true) . '</pre>';

// Get the datas we want
$product_name = $json['product']['product_name'];
$brand = $json['product']['brands'];
```

```html
<!-- Now, in HTML, this is the form with the barcode -->
	<form action="#" method="get">
		<input type="text" class="form-control" placeholder="barcode" name="ean13">
		<input type="submit" name="Ok" value="Find this Barcode !">
	</form>
```

```php
// Display the datas
echo "Product Name : ". $product_name . "<br />";
echo "Brand : ". $brand . "<br />";
```