<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * With SINGLE_TABLE inheritance all children share the table of the root, so
 * only the root owns the auto-increment column.
 */
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string', length: 16)]
#[ORM\DiscriminatorMap([
    'root'   => SingleTableRoot::class,
    'childA' => SingleTableChildA::class,
    'childB' => SingleTableChildB::class,
])]
class SingleTableRoot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;
}
