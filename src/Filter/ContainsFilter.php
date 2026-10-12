<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareInterface;
use ApiPlatform\Doctrine\Common\Filter\ManagerRegistryAwareInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * @todo extensive tests
 *
 * Filters entities by their jsonb (Postgres-only) fields, if they contain the search parameter,
 * using the @> operator
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class ContainsFilter implements FilterInterface, LoggerAwareInterface, ManagerRegistryAwareInterface, OpenApiParameterFilterInterface
{
    use FilterTrait;

    protected function filterParameter(
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $property = $this->getParameterProperty($parameter);
        if (!$this->isPropertyMapped($property, $resourceClass)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::LEFT_JOIN);
        }

        // @todo very primitive handling of multiple values, is there a better way in Postgres, e.g.
        //       to give the array directly to the @> operator? Also, is it possible to filter for
        //       Records that "contain one of" the given values instead of "contains all the
        //       values"?
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

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter|array|null
    {
        return [
            new OpenApiParameter($parameter->getKey(), 'query'),
            new OpenApiParameter(
                $parameter->getKey().'[]',
                'query',
                style: 'deepObject',
                explode: true
            ),
        ];
    }
}
