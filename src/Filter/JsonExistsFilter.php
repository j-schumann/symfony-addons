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
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * @todo extensive tests
 *
 * Filters entities by their jsonb (Postgres-only) fields, if they contain
 * the search values. Multiple values are combined with AND (?& operator,
 * default) or OR (?| operator), see $combination. For a single value both
 * behave like the ? operator.
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class JsonExistsFilter implements FilterInterface, LoggerAwareInterface, ManagerRegistryAwareInterface, OpenApiParameterFilterInterface, PropertyAwareFilterInterface
{
    use FilterTrait {
        FilterTrait::__construct as private initFilter;
    }

    /**
     * Records must contain all the values.
     */
    public const string AND = 'and';

    /**
     * Records must contain at least one of the values.
     */
    public const string OR = 'or';

    /**
     * DQL functions of vrok/doctrine-addons >= 3.1 per combination.
     */
    private const array FUNCTIONS = [
        self::AND => 'JSON_CONTAINS_ALL_TEXT',
        self::OR  => 'JSON_CONTAINS_ANY_TEXT',
    ];

    /**
     * @param string $combination How multiple values are combined, self::AND or self::OR
     */
    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
        private readonly string $combination = self::AND,
    ) {
        if (!isset(self::FUNCTIONS[$combination])) {
            throw new InvalidArgumentException(\sprintf('Invalid combination "%s", use "%s" or "%s"', $combination, self::AND, self::OR));
        }

        $this->initFilter($managerRegistry, $logger, $properties, $nameConverter);
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
        if (!$this->isPropertyMapped($property, $resourceClass)) {
            return;
        }

        $value = $this->normalizeValue($value, $property);
        if (null === $value) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty(
                $property,
                $alias,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                Join::LEFT_JOIN
            );
        }

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
     * Returns the values as list of unique strings or NULL if the value is
     * invalid.
     *
     * @return list<string>|null
     */
    protected function normalizeValue(mixed $value, string $property): mixed
    {
        if (null === $value) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(
                    \sprintf('A value is required for %1$s', $property)
                ),
            ]);

            return null;
        }

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

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->getProperties();
        $properties ??= array_fill_keys(
            $this->getClassMetadata($resourceClass)->getFieldNames(),
            null
        );

        foreach (array_keys($properties) as $property) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $propertyName = $this->normalizePropertyName($property);
            $filterParameterNames = [$propertyName, $propertyName.'[]'];
            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $propertyName,
                    'required' => false,
                    'type'     => 'string',
                ];
            }
        }

        return $description;
    }
}
