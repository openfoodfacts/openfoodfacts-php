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

    public function __construct(array $data = null)
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
            $this->listDocuments[] = new Document($document);
        }
        $this->count    = $data['count'];
        $this->page     = $data['page'];
        $this->skip     = $data['skip'];
        $this->pageSize = $data['page_size'];
    }


    public function getCount() : int
    {
        return $this->count;
    }
    public function getPage() : int
    {
        return $this->page;
    }
    public function getSkip() : int
    {
        return $this->skip;
    }
    public function getPageSize() : int
    {
        return $this->pageSize;
    }


    public function pageCount()
    {
        return count($this->listDocuments);
    }

    public function searchCount()
    {
        return $this->getCount();
    }

    /**
     * Implementation of Iterator
     */



    public function rewind()
    {
        reset($this->listDocuments);
    }

    public function current()
    {
        return current($this->listDocuments);
    }

    public function key()
    {
        return key($this->listDocuments);
    }

    public function next()
    {
        return next($this->listDocuments);
    }

    public function valid()
    {
        $key = key($this->listDocuments);
        return ($key !== null && $key !== false);
    }
}
