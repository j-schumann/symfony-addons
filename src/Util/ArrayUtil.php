<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Util;

class ArrayUtil
{
    /**
     * This is used to merge arrays with regard to keeping values unique and
     * ignoring numeric keys (also meaning numeric keys are not preserved).
     * Nested, equal arrays on different keys are still kept.
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
     * This function is meant to be used for example with
     * ApiPlatformTestCase::assertDatasetHasKeys, so we can combine different
     * lists of (nested) keys that should be present in a response.
     *
     * @param ...$args 2 or more arrays to merge
     */
    public static function mergeValues(array ...$args): array
    {
        $merged = array_merge_recursive(...$args);

        // array_merge_recursive is the closest to what we want to achieve,
        // we just need to deduplicate values.
        // We cannot use array_unique($merged, SORT_REGULAR) as this would also
        // remove equal arrays on different keys, but we want to keep those.
        $deduplicate = static function (array $list) use (&$deduplicate) {
            $values = [];
            $deduplicated = [];
            foreach ($list as $k => $v) {
                if (is_array($v)) {
                    $deduplicated[$k] = $deduplicate($v);
                    continue;
                }

                if (isset($values[$v])) {
                    continue;
                }

                $deduplicated[$k] = $v;
                $values[$v] = true;
            }

            return $deduplicated;
        };

        return $deduplicate($merged);
    }

    /**
     * Returns true if the given array has duplicate values, else false.
     */
    public static function hasDuplicates(array $array): bool
    {
        // SORT_REGULAR allows to compare object/arrays
        return count($array) !== count(array_unique($array, SORT_REGULAR));
    }
}
