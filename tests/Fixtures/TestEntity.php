<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class TestEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    public string $id = '';

    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    public array $jsonColumn = [];
}
