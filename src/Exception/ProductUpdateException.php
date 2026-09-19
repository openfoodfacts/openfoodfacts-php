<?php

namespace OpenFoodFacts\Exception;

class ProductUpdateException extends BadRequestException
{
    /**
     * @param string $message
     * @param array $response the full v3 response envelope (status, result, errors, warnings, product)
     */
    public function __construct(string $message, private readonly array $response = [])
    {
        parent::__construct($message);
    }

    /**
     * The full v3 response envelope. Useful on partial failures (status
     * "success_with_errors"), where part of the product data was saved anyway.
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
