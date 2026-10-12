<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\Child;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;

class TestFixtures extends Fixture implements FixtureGroupInterface
{
    public const array RECORDS = [
        'eins' => [
            'children'      => [
                ['jsonColumn' => ['CHILD_EINS'], 'varcharColumn' => 'child EINS'],
            ],
            'jsonColumn'    => [1, 5, 'ROLE_ADMIN', 'ROLE_EDITOR'],
            'textColumn'    => 'record EINS text',
            'varcharColumn' => 'record EINS varchar',
        ],
        'zwei' => [
            'children'      => [
                ['jsonColumn' => ['CHILD_ZWEI'], 'varcharColumn' => 'child ZWEI'],
            ],
            'jsonColumn'    => [2, 'ROLE_ADMIN_BLOG'],
            'textColumn'    => 'record ZWEI text',
            'varcharColumn' => 'record ZWEI varchar',
        ],
    ];

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::RECORDS as $data) {
            $record = new TestEntity();
            $record->jsonColumn = $data['jsonColumn'];
            $record->textColumn = $data['textColumn'];
            $record->varcharColumn = $data['varcharColumn'];

            foreach ($data['children'] as $childData) {
                $child = new Child();
                $child->jsonColumn = $childData['jsonColumn'];
                $child->varcharColumn = $childData['varcharColumn'];
                $child->testEntity = $record;
                $record->children->add($child);
            }

            $manager->persist($record);
        }

        $manager->flush();
    }
}
