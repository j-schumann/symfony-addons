<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a composite primary key cannot have an auto-increment column.
 */
#[ORM\Entity]
class CompositeKeyEntity
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    public string $keyPartOne = '';

    #[ORM\Id]
    #[ORM\Column(length: 32)]
    public string $keyPartTwo = '';
}
