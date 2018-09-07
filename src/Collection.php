<?php

namespace OpenFoodFacts;

use OpenFoodFacts\Document;

class Collection
{

    private $listDocuments  = null;
    private $count          = null;
    private $page           = null;
    private $skip           = null;
    private $page_size      = null;

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
        foreach($data['products'] as $document){
            $this->listDocuments[] = new Document($document);
        }
        $this->count    = $data['count'];
        $this->page     = $data['page'];
        $this->skip     = $data['skip'];
        $this->pageSize = $data['page_size'];
    }

    public function pageCount()
    {
        return count($this->listDocuments);
    }

    public function searchCount()
    {
        return $this->count;
    }
}
