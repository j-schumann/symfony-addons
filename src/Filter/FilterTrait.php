<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareTrait;
use ApiPlatform\Doctrine\Orm\NestedPropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Dispatches apply() for a #[QueryParameter] to filterParameter() and resolves the queried fields,
 * like API Platform's own parameter-based filters.
 *
 * @internal
 */
trait FilterTrait
{
    use BackwardCompatibleFilterDescriptionTrait;
    use LoggerAwareTrait;
    use NestedPropertyHelperTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $parameter = $context['parameter'] ?? null;
        if ($parameter instanceof Parameter) {
            $this->filterParameter(
                $parameter,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                $operation,
                $context,
            );
        }
    }

    abstract protected function filterParameter(
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void;

    /**
     * Joins the associations of a nested property and returns the alias, the field and the field's
     * type to query, or NULL if the property is no mapped field.
     *
     * @param array<string, mixed>|null $nestedInfo API Platform's nested_properties_info entry for
     *                                              the property
     *
     * @return array{string, string, string}|null
     */
    protected function resolveField(
        string $property,
        ?array $nestedInfo,
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
    ): ?array {
        $class = $nestedInfo['leaf_class'] ?? $queryBuilder->getRootEntities()[0];
        $field = $nestedInfo['leaf_property']
            ?? $parameter->getExtraProperties()['query_property']
            ?? $property;

        $metadata = $queryBuilder->getEntityManager()->getClassMetadata($class);
        if (!$metadata->hasField($field)) {
            return null;
        }

        // addNestedParameterJoins() reads the first nested_properties_info entry, a parameter for
        // multiple properties has one per property
        $propertyParameter = $parameter->withExtraProperties([
            ...$parameter->getExtraProperties(),
            'nested_properties_info' => $nestedInfo ? [$property => $nestedInfo] : [],
            'query_property'         => $field,
        ]);

        [$alias, $queryField] = $this->addNestedParameterJoins(
            $property,
            $queryBuilder->getRootAliases()[0],
            $queryBuilder,
            $queryNameGenerator,
            $propertyParameter,
            Join::LEFT_JOIN
        );

        return [$alias, $queryField, $metadata->getFieldMapping($field)->type];
    }

    /**
     * Postgres rejects invalid UTF-8 and cuts text at a null character.
     */
    protected function isValidText(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8') && !str_contains($text, "\0");
    }
}
