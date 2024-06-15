<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Used to simplify code in ApiPlatform QueryExtensions but may be used
 * in other cases too. Placed in SymfonyAddons instead of DoctrineAddons
 * because of the dependency to the QueryNameGenerator.
 */
class QueryBuilderHelper
{
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly QueryNameGeneratorInterface $queryNameGenerator,
    ) {
    }

    /**
     * Returns the first (most times only) root alias for the current query.
     */
    public function getRootAlias(): string
    {
        return $this->queryBuilder->getRootAliases()[0];
    }

    /**
     * Adds a simple AND condition ($alias.$field = $param) to the where clause.
     * If no alias is given, the current root alias is used.
     */
    public function andWhereFieldEquals(string $field, mixed $param, ?string $alias = null): void
    {
        $paramName = $this->prepareParameter($field, $param);
        $alias ??= $this->getRootAlias();
        $this->queryBuilder->andWhere("$alias.$field = :$paramName");
    }

    /**
     * Adds a simple AND condition ($alias.$field != $param) to the where clause.
     * If no alias is given, the current root alias is used.
     */
    public function andWhereFieldNotEquals(string $field, mixed $param, ?string $alias = null): void
    {
        $paramName = $this->prepareParameter($field, $param);
        $alias ??= $this->getRootAlias();
        $this->queryBuilder->andWhere("$alias.$field != :$paramName");
    }

    /**
     * Adds a simple AND condition ($alias.$field IN ($param)) to the where clause.
     * If no alias is given, the current root alias is used.
     */
    public function andWhereFieldIn(string $field, array $param, ?string $alias = null): void
    {
        $paramName = $this->prepareParameter($field, $param);
        $alias ??= $this->getRootAlias();
        $this->queryBuilder->andWhere("$alias.$field IN (:$paramName)");
    }

    /**
     * Returns the alias of the given relation from the queried entity
     * (e.g. "objectRoles" of User).
     * If the relation is not (yet) joined, NULL is returned.
     * If the relation is not (yet) joined but autoCreate is TRUE,
     * a new LEFT JOIN is added to the query and the alias returned.
     */
    public function getJoinAlias(string $relation, bool $autoCreate = false): ?string
    {
        $rootAlias = $this->getRootAlias();
        $joinAlias = null;

        // get all current joins
        $joinDqlPart = $this->queryBuilder->getDQLPart('join');

        foreach ($joinDqlPart as $root => $joins) {
            if ($root !== $rootAlias) {
                continue;
            }

            /** @var Join $join */
            foreach ($joins as $join) {
                if ("$rootAlias.$relation" === $join->getJoin()) {
                    $joinAlias = $join->getAlias();
                    break 2;
                }
            }
        }

        if ($autoCreate && !$joinAlias) {
            $joinAlias = $this->addJoin($relation);
        }

        return $joinAlias;
    }

    /**
     * Adds a LEFT JOIN to the current query for the given relation and returns
     * the generated alias for the joined table.
     */
    public function addJoin(string $relation): string
    {
        $rootAlias = $this->getRootAlias();
        $joinAlias = $this->queryNameGenerator->generateJoinAlias($relation);
        $this->queryBuilder->leftJoin("$rootAlias.$relation", $joinAlias);

        return $joinAlias;
    }

    private function prepareParameter(string $field, mixed $param): string
    {
        $paramName = $this->queryNameGenerator->generateParameterName($field);
        $this->queryBuilder->setParameter($paramName, $param);

        return $paramName;
    }
}
