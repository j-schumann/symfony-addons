<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * The table name is a reserved word on every supported platform, so the statements that purge and
 * reset it have to use the quoted name.
 */
#[ORM\Entity]
#[ORM\Table(name: '`order`')]
class ReservedWordEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;
}
