<?php

namespace OpenFoodFacts;

/**
 * Trait RecursiveSortingTrait
 */
trait RecursiveSortingTrait
{
    /**
     * @param array $arr
     * @return bool
     */
    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Sorts referenced array of arrays in a recursive way for better understandability
     * @param array $arr
     * @see ksort
     * @see asort
     */
    public function recursiveSortArray(array &$arr): void
    {
        if ($this->isAssoc($arr)) {
            ksort($arr);
        } else {
            asort($arr);
        }
        foreach ($arr as &$a) {
            if (is_array($a)) {
                $this->recursiveSortArray($a);
            }
        }
    }
}
