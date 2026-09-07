<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * A self-referencing table cannot be emptied by ordering the tables, which is why the foreign key
 * checks are disabled for the purge.
 */
#[ORM\Entity]
class SelfReferencingEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    public ?self $parent = null;
}
