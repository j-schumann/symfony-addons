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
 * the search parameter, using the ? operator. Multiple values are combined
 * with AND (?& operator, default) or OR (?| operator), see $combination.
 *
 * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE
 */
class JsonExistsFilter implements FilterInterface, LoggerAwareInterface, ManagerRegistryAwareInterface, OpenApiParameterFilterInterface, PropertyAwareFilterInterface
{
    use FilterTrait {
        FilterTrait::__construct as private initFilter;
    }

    // records must contain at least one of the values
    public const string OR = 'or';

    // records must contain all the values
    public const string AND = 'and';

    // DQL functions of vrok/doctrine-addons >= 3.1 for multiple values
    private const array MULTIPLE_VALUES_FUNCTIONS = [
        self::OR  => 'JSON_CONTAINS_ANY_TEXT',
        self::AND => 'JSON_CONTAINS_ALL_TEXT',
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
        if (!isset(self::MULTIPLE_VALUES_FUNCTIONS[$combination])) {
            throw new InvalidArgumentException(\sprintf('Invalid combination "%s", use "%s" or "%s"', $combination, self::OR, self::AND));
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

        $function = \is_array($value)
            ? self::MULTIPLE_VALUES_FUNCTIONS[$this->combination]
            : 'JSON_CONTAINS_TEXT';

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::LEFT_JOIN);
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        $queryBuilder
            ->andWhere(\sprintf('%s(%s.%s, :%s) = true', $function, $alias, $field, $valueParameter))
            ->setParameter($valueParameter, $value);
    }

    /**
     * Returns a single value as string, multiple values as list of unique
     * strings or NULL if the value is invalid.
     *
     * @return string|list<string>|null
     */
    protected function normalizeValue(mixed $value, string $property): mixed
    {
        if (null === $value) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(\sprintf('A value is required for %1$s', $property)),
            ]);

            return null;
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_array($value) && [] !== $value && array_all($value, static fn (mixed $item): bool => \is_scalar($item))) {
            $values = array_values(array_unique(array_map(strval(...), $value)));

            // a single value does not need the operators for multiple values
            return 1 === \count($values) ? $values[0] : $values;
        }

        $this->getLogger()->notice('Invalid filter ignored', [
            'exception' => new InvalidArgumentException(\sprintf('Invalid value for "%s" property', $property)),
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
        $properties ??= array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);

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
