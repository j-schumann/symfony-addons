<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareTrait;
use ApiPlatform\Doctrine\Common\Filter\ManagerRegistryAwareTrait;
use ApiPlatform\Doctrine\Common\PropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\PropertyHelperTrait as OrmPropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Replaces the deprecated AbstractFilter of API Platform: provides its helpers and dispatches
 * apply() for a #[QueryParameter] to filterParameter().
 *
 * @internal
 */
trait FilterTrait
{
    use BackwardCompatibleFilterDescriptionTrait;
    use LoggerAwareTrait;
    use ManagerRegistryAwareTrait;
    use OrmPropertyHelperTrait;
    use PropertyHelperTrait;

    protected ?NameConverterInterface $nameConverter = null;

    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->logger = $logger;
        $this->nameConverter = $nameConverter;
    }

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
            $this->filterParameter($parameter, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
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
     * Returns the property the parameter names, its key if it names none.
     */
    protected function getParameterProperty(Parameter $parameter): string
    {
        return $this->denormalizePropertyName($parameter->getProperty() ?? $parameter->getKey());
    }

    protected function denormalizePropertyName(string|int $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return (string) $property;
        }

        return implode('.', array_map($this->nameConverter->denormalize(...), explode('.', (string) $property)));
    }
}
