<?php

namespace OpenFoodFacts\Exception;

use Exception;

/**
 * Just an exception class for the try catch
 */
class ProductNotFoundException extends Exception
{
    protected $message = "Product not found";
}
