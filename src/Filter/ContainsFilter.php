<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareInterface;
use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters entities by their jsonb (Postgres-only) fields, if they contain the search parameter,
 * using the @> operator.
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class ContainsFilter implements
    FilterInterface,
    LoggerAwareInterface,
    OpenApiParameterFilterInterface
{
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

        // @todo one condition per value, could a single @> with a JSON array of all values replace
        //       them?
        foreach ((array) $parameter->getValue() as $singleValue) {
            // @> requires JSON: keep numbers, quoted strings etc., encode plain strings like ROLE_A
            $json = \is_string($singleValue) && json_validate($singleValue)
                ? $singleValue
                : json_encode($singleValue, \JSON_THROW_ON_ERROR);
            $valueParameter = $queryNameGenerator->generateParameterName($field);

            $queryBuilder
                ->andWhere(\sprintf('CONTAINS(%s.%s, :%s) = true', $alias, $field, $valueParameter))
                ->setParameter($valueParameter, $json);
        }
    }
}
