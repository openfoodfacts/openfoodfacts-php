<?php

namespace OpenFoodFacts;

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
     * Initialization the document and specify from which API it was extract
     * @param array $data the whole data
     * @param string|null $api the api name
     */
    public function __construct(array $data, string $api = null)
    {
        $this->recursiveSortArray($data);
        $this->data = $data;
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
     * @return Document
     */
    public static function createSpecificDocument(string $apiIdentifier, array $data): Document
    {
        if ($apiIdentifier === '') {
            return new Document($data);
        }

        $className = "OpenFoodFacts\Document\\" . ucfirst($apiIdentifier) . 'Document';

        if (class_exists($className) && is_subclass_of($className, Document::class)) {
            return new $className($data);
        }

        return new Document($data);
    }
}
