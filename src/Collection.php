<?php

namespace OpenFoodFacts;

/**
 * @phpstan-implements \Iterator<number, Document>
 */
class Collection implements \Iterator
{
    public const defaultPageSize = 24;

    /** @var array<int, Document> */
    private array $listDocuments  = [];
    private int $count          = 0;
    private int$page           = 0;
    private int$skip           = 0;
    private int $pageSize       = 0;

    /**
     * initialization of the collection
     * @param array|null $data the raw data
     * @param string|null $api  this information help to type the collection  (not use yet)
     */
    public function __construct(?array $data = null, ?string $api = null)
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
                if ($document instanceof Document) {
                    $this->listDocuments[] = $document;
                } elseif (is_array($document)) {
                    $this->listDocuments[] = Document::createSpecificDocument($currentApi, $document);
                } else {
                    throw new \InvalidArgumentException(sprintf('Would expect an OpenFoodFacts\Document Interface or Array here. Got: %s', gettype($document)));
                }
            }
        }

        $this->count    = $data['count'] ?? null;
        $this->page     = $data['page'] ?? 0;
        $this->skip     = $data['skip'] ?? 0;
        $this->pageSize = $data['page_size'] ?? 0;
    }

    /**
     * @return int get the current page
     */
    public function getPage(): int
    {
        return $this->page;
    }
    /**
     * @return int get the number of element skipped
     */
    public function getSkip(): int
    {
        return $this->skip;
    }
    /**
     * @return int get the number of element by page for this collection
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * @return int the number of element in this Collection
     */
    public function pageCount(): int
    {
        return count($this->listDocuments);
    }

    /**
     * @return int the number of element for this search
     */
    public function searchCount(): int
    {
        return $this->count;
    }

    /**
     * Implementation of Iterator
     */

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        reset($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function current(): Document|false
    {
        return current($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function key(): int|null
    {
        return key($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function next(): void
    {
        next($this->listDocuments);
    }
    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        $key = key($this->listDocuments);

        return ($key !== null && $key !== false);
    }
}
