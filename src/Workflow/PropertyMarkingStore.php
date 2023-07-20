<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Workflow;

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

class PropertyMarkingStore implements MarkingStoreInterface
{
    public function __construct(
        private readonly bool $singleState = false,
        private readonly string $property = 'marking'
    ) {
    }

    public function getMarking(object $subject): Marking
    {
        $marking = $subject->{$this->property};

        if (null === $marking) {
            return new Marking();
        }

        if ($this->singleState) {
            $marking = [(string) $marking => 1];
        } elseif (!\is_array($marking)) {
            throw new LogicException(sprintf('The property "%s::%s" did not contain an array and the Workflow\'s Marking store is instantiated with $singleState=false.', get_debug_type($subject), $this->property));
        }

        return new Marking($marking);
    }

    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $newValue = $marking->getPlaces();

        if ($this->singleState) {
            $newValue = key($newValue);
        }

        $subject->{$this->property} = $newValue;
    }
}
