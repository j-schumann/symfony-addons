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
 * Filters entities by their jsonb (Postgres-only) fields, if they contain the search parameter,
 * using the @> operator. Multiple values are combined with AND (default) or OR, see
 * $combination.
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class ContainsFilter implements FilterInterface, LoggerAwareInterface, OpenApiParameterFilterInterface
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
        $nestedPropertiesInfo = $parameter->getExtraProperties()['nested_properties_info'] ?? [];
        $resolved = $this->resolveField(
            $parameter->getProperty() ?? $parameter->getKey() ?? '',
            reset($nestedPropertiesInfo) ?: null,
            $parameter,
            $queryBuilder,
            $queryNameGenerator,
        );
        if (null === $resolved) {
            return;
        }

        [$alias, $field] = $resolved;

        $conditions = [];
        foreach ((array) $parameter->getValue() as $singleValue) {
            // @> requires JSON: keep numbers, quoted strings etc., encode plain strings like ROLE_A
            $json = \is_string($singleValue) && json_validate($singleValue)
                ? $singleValue
                : json_encode($singleValue, \JSON_THROW_ON_ERROR);
            $valueParameter = $queryNameGenerator->generateParameterName($field);

            $conditions[] = \sprintf(
                'CONTAINS(%s.%s, :%s) = true',
                $alias,
                $field,
                $valueParameter
            );
            $queryBuilder->setParameter($valueParameter, $json);
        }

        // an empty composite would render a dangling WHERE
        if ([] === $conditions) {
            return;
        }

        $queryBuilder->andWhere(
            self::OR === $this->combination
                ? $queryBuilder->expr()->orX(...$conditions)
                : $queryBuilder->expr()->andX(...$conditions)
        );
    }
}
