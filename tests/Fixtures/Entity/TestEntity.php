<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource]
#[ORM\Entity]
class TestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    public array $jsonColumn = [];

    #[ORM\Column(type: Types::TEXT)]
    public string $textColumn = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $varcharColumn = '';

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
