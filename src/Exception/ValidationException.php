<?php

namespace OpenFoodFacts\Exception;

use Exception;

/**
 * Just an exception class for the try catch
 */
class ValidationException extends Exception
{
    /** @var string  */
    protected $message = 'Validation error on search api';
}
