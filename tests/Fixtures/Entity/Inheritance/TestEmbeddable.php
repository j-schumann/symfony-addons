<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * An embeddable has no table of its own and must not end up in the list of
 * tables to purge / to reset.
 */
#[ORM\Embeddable]
class TestEmbeddable
{
    #[ORM\Column]
    public string $embeddedColumn = '';
}
