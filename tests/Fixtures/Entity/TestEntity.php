<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Vrok\SymfonyAddons\Filter\ContainsFilter;
use Vrok\SymfonyAddons\Filter\JsonExistsFilter;
use Vrok\SymfonyAddons\Filter\SimpleSearchFilter;

#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['none']]),
        // dispatches the entity to the message bus instead of persisting it
        new Post(
            status: 202,
            output: false,
            messenger: true
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['none']],
            parameters: [
                'pattern'          => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    description: 'Search in the text and varchar column',
                    properties: ['textColumn', 'varcharColumn'],
                ),
                'childName'        => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    properties: ['children.varcharColumn'],
                ),
                // JSON is cast to text on Postgres
                'jsonSearch'       => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    properties: ['jsonColumn'],
                ),
                'childJsonSearch'  => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    properties: ['children.jsonColumn'],
                ),
                // unmapped properties are skipped
                'searchUnmapped'   => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    properties: ['unknownColumn', 'varcharColumn'],
                ),
                'onlyUnmapped'     => new QueryParameter(
                    filter: new SimpleSearchFilter(),
                    properties: ['unknownColumn'],
                ),
                'containsUnmapped' => new QueryParameter(
                    filter: new ContainsFilter(),
                    property: 'unknownColumn',
                ),
                // Postgres only
                'contains'         => new QueryParameter(
                    filter: new ContainsFilter(),
                    property: 'jsonColumn',
                ),
                'hasAnyKey'        => new QueryParameter(
                    filter: new JsonExistsFilter(combination: JsonExistsFilter::OR),
                    property: 'jsonColumn',
                ),
                'hasKey'           => new QueryParameter(
                    filter: new JsonExistsFilter(),
                    property: 'jsonColumn',
                ),
                'childContains'    => new QueryParameter(
                    filter: new ContainsFilter(),
                    property: 'children.jsonColumn',
                ),
                'childHasKey'      => new QueryParameter(
                    filter: new JsonExistsFilter(),
                    property: 'children.jsonColumn',
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

    #[ORM\Column(type: Types::JSONB)]
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
