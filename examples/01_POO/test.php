<?php

require 'vendor/autoload.php';

$test = new \OpenFoodFacts\Provider\Api('food','fr');



$prd = $test->getProduct('3057640385148');

var_dump($prd);


var_dump($test->getProduct('305764038514800'));
//"https://static.openfoodfacts.org/images/products/305/764/038/5148/front_fr.75.400.jpg"
