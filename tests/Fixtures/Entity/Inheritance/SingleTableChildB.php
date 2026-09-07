<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SingleTableChildB extends SingleTableRoot
{
    #[ORM\Column(nullable: true)]
    public ?string $childBColumn = null;
}
