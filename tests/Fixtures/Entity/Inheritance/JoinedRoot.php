<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * With JOINED inheritance each child has its own table, but its primary key is
 * a foreign key to the root and not auto-increment, although the children
 * inherit generatorType = IDENTITY,
 * @see \Doctrine\ORM\Mapping\ClassMetadataFactory::inheritIdGeneratorMapping()
 */
#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string', length: 16)]
#[ORM\DiscriminatorMap([
    'root'   => JoinedRoot::class,
    'childA' => JoinedChildA::class,
    'childB' => JoinedChildB::class,
])]
class JoinedRoot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;
}
