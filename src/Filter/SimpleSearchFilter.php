<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;

/**
 * Selects entities where the search term is found (case insensitive) in at least one of the
 * specified properties. All specified properties must be string or JSON columns.
 */
class SimpleSearchFilter implements FilterInterface, LoggerAwareInterface, OpenApiParameterFilterInterface
{
    use FilterTrait;

    /**
     * Column types that are cast to text before searching them on Postgres.
     */
    private const array JSON_TYPES = [
        'json',

        // DBAL has dedicated types for jsonb and JSON objects
        'json_object',
        'jsonb',
        'jsonb_object',
    ];

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
        $value = $parameter->getValue();
        if (!$this->isValidValue($value, $parameter->getKey() ?? '')) {
            return;
        }

        $platform = $queryBuilder->getEntityManager()->getConnection()->getDatabasePlatform();
        $isPostgres = $platform instanceof PostgreSQLPlatform;
        $nestedPropertiesInfo = $parameter->getExtraProperties()['nested_properties_info'] ?? [];

        // % and _ (and [ on SQL Server) match literally, "!" instead of the default backslash,
        // which MySQL also treats as escape in literals
        $pattern = '%'.$platform->escapeStringForLike((string) $value, '!').'%';

        $orExp = $queryBuilder->expr()->orX();
        foreach ($parameter->getProperties() ?? [] as $property) {
            $resolved = $this->resolveField(
                $property,
                $nestedPropertiesInfo[$property] ?? null,
                $parameter,
                $queryBuilder,
                $queryNameGenerator,
            );
            if (null === $resolved) {
                continue;
            }

            [$alias, $field, $type] = $resolved;
            $expression = $isPostgres && \in_array($type, self::JSON_TYPES, true)
                ? "CAST($alias.$field, 'text')"
                : "$alias.$field";

            // both sides lowercased by the database, so the case mapping is the same
            $parameterName = $queryNameGenerator->generateParameterName($field);
            $orExp->add("LOWER($expression) LIKE LOWER(:$parameterName) ESCAPE '!'");
            $queryBuilder->setParameter($parameterName, $pattern);
        }

        if (0 === $orExp->count()) {
            return;
        }

        $queryBuilder->{$context['whereClause'] ?? 'andWhere'}("($orExp)");
    }

    /**
     * Only a single valid search term is supported, arrays (e.g. ?pattern[]=foo) are ignored and
     * logged, like API Platform does for invalid filter values.
     */
    private function isValidValue(mixed $value, string $parameterName): bool
    {
        if (!\is_scalar($value) || !$this->isValidText((string) $value)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(\sprintf(
                    'Invalid value for "%s" parameter, only a single valid search term is supported',
                    $parameterName,
                )),
            ]);

            return false;
        }

        return true;
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter|array|null
    {
        return new OpenApiParameter($parameter->getKey() ?? '', 'query');
    }
}
