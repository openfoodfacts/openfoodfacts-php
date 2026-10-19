<?php

namespace OpenFoodFacts\Exception;

class ValidationException extends ApiException
{
    /** @var string  */
    protected $message = 'Validation error on search api';
}
