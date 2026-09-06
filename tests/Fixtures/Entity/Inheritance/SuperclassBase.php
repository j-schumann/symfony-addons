<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * A mapped superclass has no table of its own, the concrete entities that inherit the identifier
 * have one each.
 */
#[ORM\MappedSuperclass]
abstract class SuperclassBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $varcharColumn = '';
}
