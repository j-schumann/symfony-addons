<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\ApiFilter;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Vrok\SymfonyAddons\Filter\ContainsFilter;
use Vrok\SymfonyAddons\Filter\JsonExistsFilter;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;
use Vrok\SymfonyAddons\Tests\Fixtures\JsonbColumn;

/**
 * Uses the filters via the legacy #[ApiFilter] attribute, which is deprecated
 * since API Platform 4.4. Only loaded in the "test_api_filter" environment, so
 * the deprecation is only triggered by the tests for this entity.
 *
 * @todo remove when support for #[ApiFilter] is dropped (API Platform 6)
 */
#[ApiResource(operations: [
    new GetCollection(normalizationContext: ['groups' => ['none']]),
])]
#[ApiFilter(
    filterClass: SimpleSearchFilter::class,
    properties: ['textColumn', 'varcharColumn', 'parent.varcharColumn'],
    arguments: ['searchParameterName' => 'pattern'],
)]
#[ApiFilter(filterClass: ContainsFilter::class, properties: ['numbers'])]
#[ApiFilter(filterClass: JsonExistsFilter::class, properties: ['roles'])]
#[ORM\Entity]
class ApiFilterEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    public string $textColumn = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $varcharColumn = '';

    #[ORM\Column(type: JsonbColumn::TYPE, options: JsonbColumn::OPTIONS)]
    public array $numbers = [];

    #[ORM\Column(type: JsonbColumn::TYPE, options: JsonbColumn::OPTIONS)]
    public array $roles = [];

    // no "onDelete", SQL Server does not allow cascading actions on self-references
    #[ORM\ManyToOne(targetEntity: self::class)]
    public ?ApiFilterEntity $parent = null;
}
