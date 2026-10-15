<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;

/**
 * How a filter combines multiple values.
 *
 * @internal
 */
trait CombinationTrait
{
    /**
     * Records must contain all the values.
     */
    public const string AND = 'and';

    /**
     * Records must contain at least one of the values.
     */
    public const string OR = 'or';

    /**
     * Not promoted with readonly, so subclasses with their own constructor keep the default.
     */
    private string $combination = self::AND;

    /**
     * @param string $combination How multiple values are combined, self::AND or self::OR
     */
    public function __construct(string $combination = self::AND)
    {
        if (self::AND !== $combination && self::OR !== $combination) {
            throw new InvalidArgumentException(\sprintf('Invalid "combination" parameter "%s", use "%s" or "%s"', $combination, self::AND, self::OR));
        }

        $this->combination = $combination;
    }
}
