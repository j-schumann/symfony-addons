<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Util;

class ArrayUtil
{
    /**
     * This is used to merge arrays with regard to keeping values unique and
     * ignoring numeric keys (also meaning numeric keys are not preserved).
     *
     * Reason:
     * 1) a + $b goes by keys, if the key is already present in $a, the value
     *    from $b is skipped
     * 2) array_merge($a, $b) obviously does not work with multidimensional arrays
     * 3) array_merge_recursive($a, $b) correctly ignores the numeric keys but
     *    duplicates equal values
     * 4) array_replace_recursive goes by keys, if the key is present in $a and
     *    $b, the value from $a is overwritten
     *
     * @param ...$args 2 or more arrays to merge
     */
    public static function mergeValues(array ...$args): array
    {
        $merged = array_merge_recursive(...$args);

        // array_merge_recursive is the closest to what we want to achieve,
        // we just need to deduplicate values
        $fixMerge = static function (array $merged) use (&$fixMerge) {
            foreach ($merged as $k => $v) {
                if (is_array($v)) {
                    $merged[$k] = $fixMerge($v);
                }
            }

            // using SORT_REGULAR allows it to work with nested arrays
            return array_unique($merged, SORT_REGULAR);
        };

        return $fixMerge($merged);
    }
}
