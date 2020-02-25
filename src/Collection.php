<?php

namespace OpenFoodFacts;

use Iterator;

class Collection implements Iterator
{

    private $listDocuments  = null;
    private $count          = null;
    private $page           = null;
    private $skip           = null;
    private $pageSize       = null;

    /**
     * initialization of the collection
     * @param array|null $data the raw data
     * @param string|null $api  this information help to type the collection  (not use yet)
     */
    public function __construct(array $data = null, string $api = null)
    {
        $data = $data ?? [
            'products'  => [],
            'count'     => 0,
            'page'      => 0,
            'skip'      => 0,
            'page_size' => 0,
        ];
        $this->listDocuments = [];

        if (!empty($data['products'])) {
            $currentApi = '';
            if (null !== $api) {
                $currentApi = $api;
            }
            foreach ($data['products'] as $document) {
                $this->listDocuments[] = $this->createSpecificDocument($currentApi, $document);
            }
        }

        $this->count    = $data['count'];
        $this->page     = $data['page'];
        $this->skip     = $data['skip'];
        $this->pageSize = $data['page_size'];
    }

    /**
     * @param string $apiIdentifier
     * @param array $data
     * @return Document
     */
    protected function createSpecificDocument(string $apiIdentifier, array $data): Document
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

    /**
     * @return int get the current page
     */
    public function getPage() : int
    {
        return $this->page;
    }
    /**
     * @return int get the number of element skipped
     */
    public function getSkip() : int
    {
        return $this->skip;
    }
    /**
     * @return int get the number of element by page for this collection
     */
    public function getPageSize() : int
    {
        return $this->pageSize;
    }

    /**
     * @return int the number of element in this Collection
     */
    public function pageCount() : int
    {
        return count($this->listDocuments);
    }

    /**
     * @return int the number of element for this search
     */
    public function searchCount() : int
    {
        return $this->count;
    }

    /**
     * Implementation of Iterator
     */

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        reset($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function current()
    {
        return current($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function key()
    {
        return key($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function next()
    {
        return next($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function valid()
    {
        $key = key($this->listDocuments);
        return ($key !== null && $key !== false);
    }
}
