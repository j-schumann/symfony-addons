<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Fixtures\Entity\Inheritance;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with an assigned identifier (like projects using UUIDs) has no
 * auto-increment column that could be reset.
 */
#[ORM\Entity]
class AssignedIdEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    public string $id = '';

    #[ORM\Embedded(class: TestEmbeddable::class)]
    public TestEmbeddable $embedded;

    public function __construct()
    {
        $this->embedded = new TestEmbeddable();
    }
}
