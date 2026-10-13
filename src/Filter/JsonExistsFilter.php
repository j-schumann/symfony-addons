<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareInterface;
use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters entities by their jsonb (Postgres-only) fields, if they contain the search values.
 * Multiple values are combined with AND (?& operator, default) or OR (?| operator), see
 * $combination. For a single value both behave like the ? operator.
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class JsonExistsFilter implements
    FilterInterface,
    LoggerAwareInterface,
    OpenApiParameterFilterInterface
{
    use FilterTrait;
    use OpenApiFilterTrait;

    /**
     * Records must contain all the values.
     */
    public const string AND = 'and';

    /**
     * Records must contain at least one of the values.
     */
    public const string OR = 'or';

    /**
     * DQL functions of vrok/doctrine-addons per combination.
     */
    private const array FUNCTIONS = [
        self::AND => 'JSON_CONTAINS_ALL_TEXT',
        self::OR  => 'JSON_CONTAINS_ANY_TEXT',
    ];

    /**
     * @param string $combination How multiple values are combined, self::AND or self::OR
     */
    public function __construct(
        private readonly string $combination = self::AND,
    ) {
        if (!isset(self::FUNCTIONS[$combination])) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid "combination" parameter "%s", use "%s" or "%s"',
                $combination,
                self::AND,
                self::OR,
            ));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function filterParameter(
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $property = $parameter->getProperty() ?? $parameter->getKey() ?? '';
        $value = $this->normalizeValue($parameter->getValue(), $property);
        if (null === $value) {
            return;
        }

        $nestedPropertiesInfo = $parameter->getExtraProperties()['nested_properties_info'] ?? [];
        $resolved = $this->resolveField(
            $property,
            reset($nestedPropertiesInfo) ?: null,
            $parameter,
            $queryBuilder,
            $queryNameGenerator,
        );
        if (null === $resolved) {
            return;
        }

        [$alias, $field] = $resolved;

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        $queryBuilder
            ->andWhere(
                \sprintf(
                    '%s(%s.%s, :%s) = true',
                    self::FUNCTIONS[$this->combination],
                    $alias,
                    $field,
                    $valueParameter
                )
            )
            ->setParameter($valueParameter, $value);
    }

    /**
     * Returns the values as list of unique strings or NULL if the value is invalid.
     *
     * @return list<string>|null
     */
    protected function normalizeValue(mixed $value, string $property): mixed
    {
        if (\is_scalar($value)) {
            return [(string) $value];
        }

        if (
            \is_array($value)
            && [] !== $value
            && array_all($value, static fn (mixed $item): bool => \is_scalar($item))
        ) {
            return array_values(array_unique(array_map(strval(...), $value)));
        }

        $this->getLogger()->notice('Invalid filter ignored', [
            'exception' => new InvalidArgumentException(
                \sprintf('Invalid value for "%s" property', $property)
            ),
        ]);

        return null;
    }
}
