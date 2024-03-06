<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Workflow;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Marking;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Vrok\SymfonyAddons\Workflow\PropertyMarkingStore;

class PropertyMarkingStoreTest extends KernelTestCase
{
    public function testGetNullMarking(): void
    {
        $subject = new TestEntity();
        $subject->id = null;

        $store = new PropertyMarkingStore(true, 'id');
        $result = $store->getMarking($subject);

        self::assertInstanceOf(Marking::class, $result);
        self::assertSame([], $result->getPlaces());
    }

    public function testGetSingleMarking(): void
    {
        $subject = new TestEntity();
        $subject->varcharColumn = 'teststate';

        $store = new PropertyMarkingStore(true, 'varcharColumn');
        $result = $store->getMarking($subject);

        self::assertInstanceOf(Marking::class, $result);
        self::assertSame(['teststate' => 1], $result->getPlaces());
    }

    public function testGetMultipleMarkings(): void
    {
        $subject = new TestEntity();
        $subject->jsonColumn = ['state1' => 1, 'state2' => 1];

        $store = new PropertyMarkingStore(false, 'jsonColumn');
        $result = $store->getMarking($subject);

        self::assertInstanceOf(Marking::class, $result);
        self::assertSame($subject->jsonColumn, $result->getPlaces());
    }

    public function testGetNonArrayThrowsException(): void
    {
        $subject = new TestEntity();

        $store = new PropertyMarkingStore(false, 'varcharColumn');

        $this->expectException(LogicException::class);
        $store->getMarking($subject);
    }

    public function testSetSingleMarking(): void
    {
        $subject = new TestEntity();
        $marking = new Marking();
        $marking->mark('teststate');

        $store = new PropertyMarkingStore(true, 'varcharColumn');
        $store->setMarking($subject, $marking);

        self::assertSame('teststate', $subject->varcharColumn);
    }

    public function testSetMultipleMarkings(): void
    {
        $subject = new TestEntity();
        $marking = new Marking();
        $marking->mark('state1');
        $marking->mark('state2');

        $store = new PropertyMarkingStore(false, 'jsonColumn');
        $store->setMarking($subject, $marking);

        self::assertSame(['state1' => 1, 'state2' => 1], $subject->jsonColumn);
    }
}
