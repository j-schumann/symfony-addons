<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareInterface;
use ApiPlatform\Doctrine\Common\Filter\ManagerRegistryAwareInterface;
use ApiPlatform\Doctrine\Common\Filter\PropertyAwareFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
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
class SimpleSearchFilter implements FilterInterface, LoggerAwareInterface, ManagerRegistryAwareInterface, OpenApiParameterFilterInterface, PropertyAwareFilterInterface
{
    use FilterTrait {
        FilterTrait::__construct as private initFilter;
    }

    // DBAL >= 4.5 has dedicated types for jsonb and JSON objects
    private const array JSON_TYPES = ['json', 'json_object', 'jsonb', 'jsonb_object'];

    private const string DESCRIPTION = 'Selects entities where each search term is found somewhere in at least one of the specified properties';

    /**
     * Add configuration parameter
     * {@inheritdoc}
     *
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
        private readonly string $searchParameterName = 'pattern',
    ) {
        $this->initFilter($managerRegistry, $logger, $properties, $nameConverter);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $parameter = $context['parameter'] ?? null;
        if ($parameter instanceof Parameter) {
            $this->filterParameter($parameter, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);

            return;
        }

        // @todo remove when support for #[ApiFilter] is dropped (API Platform 6)
        foreach ($context['filters'] ?? [] as $property => $value) {
            $this->filterProperty($this->denormalizePropertyName($property), $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
        }
    }

    /**
     * Used for #[QueryParameter]: Searches the properties of the parameter or,
     * if it has none, the properties of the filter. Unmapped properties are
     * skipped, API Platform 5 adds the parameter key to the filter properties.
     */
    protected function filterParameter(
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $value = $parameter->getValue();
        if (!$this->isValidValue($value, $parameter->getKey() ?? '')) {
            return;
        }

        $properties = array_filter(
            $parameter->getProperties() ?? array_keys($this->properties ?? []),
            fn (string $property): bool => $this->isPropertyMapped($property, $resourceClass, true),
        );

        $this->addWhere(
            $queryBuilder,
            $queryNameGenerator,
            $value,
            $queryNameGenerator->generateParameterName($parameter->getKey()),
            $resourceClass,
            $properties,
        );
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
        if ($property !== $this->searchParameterName || !$this->isValidValue($value, $property)) {
            return;
        }

        $properties = array_keys($this->properties ?? []);
        foreach ($properties as $prop) {
            if (
                !$this->isPropertyEnabled($prop, $resourceClass)
                || !$this->isPropertyMapped($prop, $resourceClass, true)
            ) {
                return;
            }
        }

        $this->addWhere(
            $queryBuilder,
            $queryNameGenerator,
            $value,
            $queryNameGenerator->generateParameterName($property),
            $resourceClass,
            $properties,
        );
    }

    /**
     * Only a single search term is supported, arrays (e.g. ?pattern[]=foo) are
     * ignored and logged, like API Platform does for invalid filter values.
     */
    private function isValidValue(mixed $value, string $parameterName): bool
    {
        if (null === $value) {
            return false;
        }

        if (!\is_scalar($value)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(\sprintf('Invalid value for "%s" parameter, only a single search term is supported', $parameterName)),
            ]);

            return false;
        }

        return true;
    }

    private function addWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        mixed $value,
        string $parameterName,
        string $resourceClass,
        array $properties,
    ): void {
        if ([] === $properties) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $em =  $queryBuilder->getEntityManager();
        $platform = $em->getConnection()->getDatabasePlatform();
        $from = $queryBuilder->getRootEntities()[0];
        $classMetadata = $em->getClassMetadata($from);

        // Build OR expression
        $orExp = $queryBuilder->expr()->orX();
        foreach ($properties as $prop) {
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
                    if (\in_array($fieldMeta->type, self::JSON_TYPES, true)) {
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
                if (\in_array($fieldMeta->type, self::JSON_TYPES, true)) {
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

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter|array|null
    {
        return new OpenApiParameter($parameter->getKey(), 'query', self::DESCRIPTION);
    }

    public function getDescription(string $resourceClass): array
    {
        // API Platform 4.2 also calls this for a #[QueryParameter], which can
        // name the properties instead of the filter
        $props = $this->getProperties();
        if (null === $props) {
            return [];
        }

        return [
            $this->searchParameterName => [
                'property' => implode(', ', array_keys($props)),
                'type'     => 'string',
                'required' => false,
                'openapi'  => new OpenApiParameter($this->searchParameterName, 'query', self::DESCRIPTION),
            ],
        ];
    }
}
