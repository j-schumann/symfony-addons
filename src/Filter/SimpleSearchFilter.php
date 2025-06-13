<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Selects entities where the search term is found (case insensitive) in at least
 * one of the specified properties.
 * All specified properties type must be string.
 *
 * @todo UnitTests w/ Mariadb + Postgres
 */
class SimpleSearchFilter extends AbstractFilter
{
    /**
     * Add configuration parameter
     * {@inheritdoc}
     *
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
        private readonly string $searchParameterName = 'pattern'
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (null === $value || $property !== $this->searchParameterName) {
            return;
        }

        $this->addWhere(
            $queryBuilder,
            $queryNameGenerator,
            $value,
            $queryNameGenerator->generateParameterName($property),
            $resourceClass
        );
    }

    private function addWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        mixed $value,
        string $parameterName,
        string $resourceClass,
    ): void {
        $alias = $queryBuilder->getRootAliases()[0];

        $em =  $queryBuilder->getEntityManager();
        $platform = $em->getConnection()->getDatabasePlatform();
        $from = $queryBuilder->getRootEntities()[0];
        $classMetadata = $em->getClassMetadata($from);

        // Build OR expression
        $orExp = $queryBuilder->expr()->orX();
        foreach ($this->getProperties() as $prop => $_) {
            if (
                null === $value
                || !$this->isPropertyEnabled($prop, $resourceClass)
                || !$this->isPropertyMapped($prop, $resourceClass, true)
            ) {
                return;
            }

            // @todo refactor to deduplicate code
            if ($this->isPropertyNested($prop, $resourceClass)) {
                [$joinAlias, $field, $associations] = $this->addJoinsForNestedProperty(
                    $prop,
                    $alias,
                    $queryBuilder,
                    $queryNameGenerator,
                    $resourceClass,
                    Join::LEFT_JOIN
                );

                $metadata = $this->getNestedMetadata($resourceClass, $associations);

                // special handling for JSON fields on Postgres
                if ($platform instanceof PostgreSQLPlatform) {
                    $fieldMeta = $metadata->getFieldMapping($field);
                    if ('json' === $fieldMeta['type']) {
                        $orExp->add($queryBuilder->expr()->like(
                            "LOWER(CAST($joinAlias.$field, 'text'))",
                            ":$parameterName"
                        ));
                        continue;
                    }
                }

                $orExp->add($queryBuilder->expr()->like(
                    "LOWER($joinAlias.$field)",
                    ":$parameterName"
                ));
                continue;
            }

            // special handling for JSON fields on Postgres
            if ($platform instanceof PostgreSQLPlatform) {
                $fieldMeta = $classMetadata->getFieldMapping($prop);
                if ('json' === $fieldMeta['type']) {
                    $orExp->add($queryBuilder->expr()->like(
                        "LOWER(CAST($alias.$prop, 'text'))",
                        ":$parameterName"
                    ));
                    continue;
                }
            }

            $orExp->add($queryBuilder->expr()->like("LOWER($alias.$prop)", ":$parameterName"));
        }

        $queryBuilder
            ->andWhere("($orExp)")
            ->setParameter($parameterName, '%'.strtolower((string) $value).'%');
    }

    public function getDescription(string $resourceClass): array
    {
        $props = $this->getProperties();
        if (null === $props) {
            throw new InvalidArgumentException('Properties must be specified');
        }

        return [
            $this->searchParameterName => [
                'property' => implode(', ', array_keys($props)),
                'type'     => 'string',
                'required' => false,
                'openapi'  => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ];
    }
}
