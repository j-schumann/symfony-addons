<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Selects entities where the search term is found (case insensitive) in at least
 * one of the specified properties.
 * All specified properties type must be string.
 *
 * @todo UnitTests w/ Mariadb + Postgres
 */
class SimpleSearchFilter extends AbstractContextAwareFilter
{
    /**
     * Add configuration parameter
     * {@inheritdoc}
     *
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(ManagerRegistry $managerRegistry, ?RequestStack $requestStack = null, LoggerInterface $logger = null, array $properties = null, NameConverterInterface $nameConverter = null, private readonly string $searchParameterName = 'pattern')
    {
        parent::__construct($managerRegistry, $requestStack, $logger, $properties, $nameConverter);
    }

    /** {@inheritdoc} */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (null === $value || $property !== $this->searchParameterName) {
            return;
        }

        $this->addWhere($queryBuilder, $value, $queryNameGenerator->generateParameterName($property));
    }

    private function addWhere($queryBuilder, $value, $parameterName)
    {
        $alias = $queryBuilder->getRootAliases()[0];

        // Build OR expression
        $orExp = $queryBuilder->expr()->orX();
        foreach ($this->getProperties() as $prop => $_) {
            // @todo is the CAST required for Postgres?
            $orExp->add($queryBuilder->expr()->like("LOWER(CAST($alias.$prop, 'text'))", ":$parameterName"));
        }

        $queryBuilder
            ->andWhere("($orExp)")
            ->setParameter($parameterName, '%'.strtolower($value).'%');
    }

    /** {@inheritdoc} */
    public function getDescription(string $resourceClass): array
    {
        $props = $this->getProperties();
        if (null===$props) {
            throw new InvalidArgumentException('Properties must be specified');
        }

        return [
            $this->searchParameterName => [
                'property' => implode(', ', array_keys($props)),
                'type'     => 'string',
                'required' => false,
                'swagger'  => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ];
    }
}