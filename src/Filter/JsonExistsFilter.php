<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * @todo extensive tests
 * @todo support ?| to search for multiple values
 *
 * Filters entities by their jsonb (Postgres-only) fields, if they contain
 * the search parameter, using the ? operator
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class JsonExistsFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            !$this->isPropertyEnabled($property, $resourceClass)
            || !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $value = $this->normalizeValue($value, $property);
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::LEFT_JOIN);
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        $queryBuilder
            ->andWhere(sprintf('JSON_CONTAINS_TEXT(%s.%s, :%s) = true', $alias, $field, $valueParameter))
            ->setParameter($valueParameter, $value);
    }

    protected function normalizeValue($value, string $property): mixed
    {
        if (\is_array($value)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid value for "%s" property', $property)),
            ]);

            return null;
        }

        if (null === $value) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('A value is required for %1$s', $property)),
            ]);

            return null;
        }

        return $value;
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->getProperties();
        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach (array_keys($properties) as $property) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $propertyName = $this->normalizePropertyName($property);
            $filterParameterNames = [$propertyName, $propertyName.'[]'];
            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $propertyName,
                    'type'     => 'string',
                    'required' => false,
                ];
            }
        }

        return $description;
    }
}
