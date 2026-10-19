<?php

namespace OpenFoodFacts\Exception;

/** A barcode rejected locally, before any HTTP request. */
class InvalidBarcodeException extends InvalidParameterException
{
    public function __construct(private readonly string $barcode)
    {
        parent::__construct($barcode === ''
            ? 'Barcode is invalid: it must not be empty'
            : sprintf('Barcode "%s" is invalid: it must only contain digits', $barcode));
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }
}
