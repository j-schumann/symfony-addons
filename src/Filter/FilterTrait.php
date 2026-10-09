<?php

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Doctrine\Common\Filter\LoggerAwareTrait;
use ApiPlatform\Doctrine\Common\Filter\ManagerRegistryAwareTrait;
use ApiPlatform\Doctrine\Common\PropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\PropertyHelperTrait as OrmPropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Replaces the deprecated AbstractFilter of API Platform: provides its
 * helpers and dispatches apply() to filterProperty() for both ways to use
 * a filter: the legacy #[ApiFilter] attribute and the #[QueryParameter].
 *
 * @internal
 */
trait FilterTrait
{
    use LoggerAwareTrait;
    use ManagerRegistryAwareTrait;
    use OrmPropertyHelperTrait;
    use PropertyHelperTrait;

    protected ?array $properties = null;
    protected ?NameConverterInterface $nameConverter = null;

    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->logger = $logger;
        $this->properties = $properties;
        $this->nameConverter = $nameConverter;
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
            $property = $this->denormalizePropertyName($property);
            if (!$this->isPropertyEnabled($property, $resourceClass)) {
                continue;
            }

            $this->filterProperty($property, $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
        }
    }

    /**
     * Used for #[QueryParameter]: The parameter explicitly names the property
     * to filter, so the enabled properties of the filter are not checked.
     */
    protected function filterParameter(
        Parameter $parameter,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $property = $this->denormalizePropertyName($parameter->getProperty() ?? $parameter->getKey());
        $this->filterProperty($property, $parameter->getValue(), $queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    abstract protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void;

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    protected function isPropertyEnabled(string $property, string $resourceClass): bool
    {
        if (null === $this->properties) {
            // to ensure sanity, nested properties must still be explicitly enabled
            return !$this->isPropertyNested($property, $resourceClass);
        }

        return \array_key_exists($property, $this->properties);
    }

    protected function denormalizePropertyName(string|int $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return (string) $property;
        }

        return implode('.', array_map($this->nameConverter->denormalize(...), explode('.', (string) $property)));
    }

    protected function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map($this->nameConverter->normalize(...), explode('.', $property)));
    }
}
