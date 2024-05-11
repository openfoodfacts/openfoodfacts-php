<?php

namespace OpenFoodFacts\Model;

use OpenFoodFacts\Document\SearchDocument;

class SearchResult
{
    /** @var array<int, SearchDocument> */
    public array $listDocuments;

    public readonly int $count;
    /** @var bool if false, the value is just an approximation*/
    public readonly bool $isCountExact;
    public readonly int $page;
    public readonly int $pageSize;
    public readonly int $pageCount;
    public readonly array $debug;
    public readonly ?array $warning;
    /** @var int time it took in ms  */
    public readonly int $took;
    /** @var bool partial content if true ? */
    public readonly bool $timedOut;
    public readonly array $aggregations;

    /**
     * initialization of the collection
     * @param array $content the raw data
     */
    public function __construct(array $content)
    {
        $this->count = $content['count'] ?? 0;
        $this->isCountExact = $content['is_count_exact'] ?? false;
        $this->page = $content['page'] ?? 0;
        $this->pageSize = $content['page_size'] ?? 0;
        $this->pageCount = $content['page_count'] ?? 0;
        $this->aggregations = $content['aggregations'] ?? null;

        $this->debug = $content['debug'] ?? [];
        $this->took = $content['took'] ?? 0;
        $this->timedOut = $content['timed_out'] ?? false;
        $this->warning = $content['warnings'] ?? null;

        $this->listDocuments = array_map(
            fn (array $item) => new SearchDocument($item),
            $content['hits'] ?? []
        );
    }
}
