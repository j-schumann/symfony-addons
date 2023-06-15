<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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
}
