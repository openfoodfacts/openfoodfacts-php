<?php

namespace OpenFoodFacts\Model;

class AutocompleteOption
{
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $taxonomy_name,
    ) {
    }
}
