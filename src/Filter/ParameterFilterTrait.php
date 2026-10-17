<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\QueryBuilder;

/**
 * Implements FilterInterface for a filter used with #[QueryParameter]: apply() passes the Parameter
 * to filterParameter().
 */
trait ParameterFilterTrait
{
    use BackwardCompatibleFilterDescriptionTrait;

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
        if (!$parameter instanceof Parameter) {
            throw new \LogicException(\sprintf('%s supports only #[QueryParameter], not #[ApiFilter].', static::class));
        }

        $this->filterParameter(
            $parameter,
            $queryBuilder,
            $queryNameGenerator,
            $resourceClass,
            $operation,
            $context,
        );
    }

    abstract protected function filterParameter(
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void;
}
