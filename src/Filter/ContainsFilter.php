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
    use CombinationTrait;
    use FilterTrait;
    use OpenApiFilterTrait;

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
        $values = $this->normalizeValue($parameter->getValue(), $property);
        if (null === $values) {
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

        $conditions = [];
        foreach ($values as $json) {
            $valueParameter = $queryNameGenerator->generateParameterName($field);

            $conditions[] = \sprintf(
                'CONTAINS(%s.%s, :%s) = true',
                $alias,
                $field,
                $valueParameter
            );
            $queryBuilder->setParameter($valueParameter, $json);
        }

        $queryBuilder->{$context['whereClause'] ?? 'andWhere'}(
            self::OR === $this->combination
                ? $queryBuilder->expr()->orX(...$conditions)
                : $queryBuilder->expr()->andX(...$conditions)
        );
    }

    /**
     * Returns the values as list of JSON strings or NULL if the value is invalid.
     *
     * @return non-empty-list<string>|null
     */
    protected function normalizeValue(mixed $value, string $property): ?array
    {
        $values = [];
        foreach ((array) $value as $singleValue) {
            // @> requires JSON: keep numbers, quoted strings etc., encode plain strings like ROLE_A
            $json = \is_string($singleValue) && json_validate($singleValue)
                ? $singleValue
                : json_encode($singleValue);

            // false for invalid UTF-8
            if (false === $json || !$this->isAcceptedByJsonb($json)) {
                $values = [];
                break;
            }

            $values[] = $json;
        }

        if ([] === $values) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(
                    \sprintf('Invalid value for "%s" property', $property)
                ),
            ]);

            return null;
        }

        return $values;
    }

    /**
     * PCRE errors, e.g. from the JIT stack limit, count as rejected.
     */
    private function isAcceptedByJsonb(string $json): bool
    {
        // jsonb rejects \u0000, unless its backslash is escaped
        if (0 !== preg_match('/(?<!\\\\)(?:\\\\\\\\)*+\\\\u0000/', $json)) {
            return false;
        }

        // numeric allows 131072 digits before and 16383 after the decimal point, exponents below
        // 10000 and digit runs below 5000 always fit
        $numbers = preg_replace('/"(?:[^"\\\\]++|\\\\.)*+"/', '', $json);

        return null !== $numbers
            && 0 === preg_match('/[eE][+-]?0*[1-9]\\d{4,}|\\d{5000,}/', $numbers);
    }
}
