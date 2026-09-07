<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class JoinedChildB extends JoinedRoot
{
    #[ORM\Column(nullable: true)]
    public ?string $childBColumn = null;
}
