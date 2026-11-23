<?php

namespace OpenFoodFacts;

/**
 * Trait RecursiveSortingTrait
 */
trait RecursiveSortingTrait
{
    /**
     * Sorts the keys of a referenced array recursively for better understandability.
     *
     * Only maps (arrays whose keys are not 0..n-1 in order) are sorted, by key.
     * Lists are left in their original order: in product data the order of a
     * list is meaningful (e.g. ingredients by decreasing proportion, taxonomy
     * hierarchies), and reordering a list would also turn it into a JSON object.
     * @param array $arr
     * @see ksort
     */
    public function recursiveSortArray(array &$arr): void
    {
        if (!array_is_list($arr)) {
            ksort($arr);
        }
        foreach ($arr as &$a) {
            if (is_array($a)) {
                $this->recursiveSortArray($a);
            }
        }
    }
}
