<?php

namespace OpenFoodFacts;

use OpenFoodFacts\Document\BeautyDocument;
use OpenFoodFacts\Document\FoodDocument;
use OpenFoodFacts\Document\PetDocument;
use OpenFoodFacts\Document\ProductDocument;

/**
 * In mongoDB all element are object, it not possible to define property.
 * All property of the mongodb entity are store in one property of this class and the magic call try to access to it
 * @property string $code
 * @property string $product_name
 */
class Document
{
    use RecursiveSortingTrait;

    /**
     * the whole data
     */
    private array $data;

    /**
     * the whole data
     */
    private ?string $api;

    /**
     * Initialization the document and specify from which API it was extract
     * @param array $data the whole data
     * @param string|null $api the api name
     */
    public function __construct(array $data, ?string $api = null)
    {
        // performance issues
        $this->recursiveSortArray($data);
        $this->data = $data;
        $this->api  = $api;
    }

    /**
     * @inheritDoc
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->data[$name];
    }
    /**
     * @inheritDoc
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Returns a sorted representation of the complete Document Data
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Returns a Document in the type regarding to the API used.
     * May be a Child of "Document" e.g.: FoodDocument or ProductDocument
     * @param string $apiIdentifier
     * @param array  $data
     * @return Document|BeautyDocument|FoodDocument|PetDocument|ProductDocument
     */
    public static function documentFactory(string $apiIdentifier, array $data)
    {
        $className = Document::class;
        switch ($apiIdentifier) {
            case 'beauty':
                $className = BeautyDocument::class;
                break;
            case 'food':
                $className = FoodDocument::class;
                break;
            case 'pet':
                $className = PetDocument::class;
                break;
            case 'product':
                $className = ProductDocument::class;
                break;
        }

        return new $className($data, $apiIdentifier);
    }
}
