<?php

namespace OpenFoodFacts\Exception;

use Exception;

class ValidationException extends Exception
{
    /** @var string  */
    protected $message = 'Validation error on search api';
}
