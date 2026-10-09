<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;

#[ApiResource]
#[ApiResource(
    uriTemplate: '/search_test_entities',
    shortName: 'SearchTestEntity',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['none']],
            parameters: [
                // the parameter names the properties to search
                'pattern' => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    properties: ['textColumn', 'varcharColumn'],
                ),
                // the filter names the properties to search
                'search'  => new QueryParameter(
                    filter: new SimpleSearchFilter(properties: ['varcharColumn' => null]),
                ),
            ],
        ),
    ],
)]
#[ORM\Entity]
class TestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: Types::JSON, options: ['JSONB' => true])]
    public array $jsonColumn = [];

    #[ORM\Column(type: Types::TEXT)]
    public string $textColumn = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $varcharColumn = '';

    /**
     * @var Collection<int, Child>|Child[]
     */
    #[ORM\OneToMany(
        targetEntity: Child::class,
        mappedBy: 'testEntity',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    public Collection $children;

    // region Workflow tests
    public function setState(string $state): void
    {
        $this->varcharColumn = $state;
    }

    public function getState(): string
    {
        return $this->varcharColumn;
    }
    // endregion

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }
}
