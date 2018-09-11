<?php

namespace OpenFoodFacts;

use OpenFoodFacts\Document;

class Collection implements \Iterator
{

    private $listDocuments  = null;
    private $count          = null;
    private $page           = null;
    private $skip           = null;
    private $pageSize       = null;

    /**
     * initilization of the collection
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
        foreach ($data['products'] as $document) {
            $this->listDocuments[] = new Document($document, $api);
        }
        $this->count    = $data['count'];
        $this->page     = $data['page'];
        $this->skip     = $data['skip'];
        $this->pageSize = $data['page_size'];
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
