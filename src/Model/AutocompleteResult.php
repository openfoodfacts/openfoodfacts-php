<?php

namespace OpenFoodFacts\Model;

class AutocompleteResult
{
    /** @var int time it took in ms  */
    public readonly int $took;
    /** @var bool partial content if true ? */
    public readonly bool $timedOut;
    public readonly array $debug;
    public readonly array $options;

    public function __construct(
        array $data,
    ) {
        $this->took = $data['took'] ?? 0;
        $this->timedOut = $data['timed_out'] ?? false;
        $this->debug = $data['debug'] ?? [];
        $this->options = array_map(
            fn (array $item) => new AutocompleteOption(
                $item['id'],
                $item['text'],
                $item['taxonomy_name'],
            ),
            $data['options'] ?? []
        );
    }
}
