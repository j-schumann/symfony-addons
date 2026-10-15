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
use Doctrine\DBAL\Platforms\SQLitePlatform;
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
        $parameterName = $queryNameGenerator->generateParameterName($parameter->getKey() ?? '');

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

            // "!" instead of the default backslash, which MySQL also treats as escape in literals
            $orExp->add("LOWER($expression) LIKE :$parameterName ESCAPE '!'");
        }

        if (0 === $orExp->count()) {
            return;
        }

        // SQLite's LOWER() only lowercases ASCII, mb_strtolower() would turn "Ü" into "ü", which
        // never matches the unchanged "Ü" in the column
        $term = $platform instanceof SQLitePlatform
            ? strtolower((string) $value)
            : mb_strtolower((string) $value);

        // % and _ match literally
        $term = strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']);

        $queryBuilder
            ->{$context['whereClause'] ?? 'andWhere'}("($orExp)")
            ->setParameter($parameterName, "%$term%");
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
