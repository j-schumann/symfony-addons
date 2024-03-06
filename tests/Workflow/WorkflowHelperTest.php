<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Workflow;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Vrok\SymfonyAddons\Workflow\WorkflowHelper;

class WorkflowHelperTest extends KernelTestCase
{
    public function testGetTransitionList(): void
    {
        $subject = new TestEntity();
        $subject->varcharColumn = 'draft';

        $workflow = static::getContainer()->get('state_machine.demo');
        $list = WorkflowHelper::getTransitionList($subject, $workflow);

        self::assertSame(['review' => ['blockers' => []]], $list);
    }

    public function testGetTransitionWithBlocker(): void
    {
        $subject = new TestEntity();
        $subject->varcharColumn = 'draft';

        /** @var EventDispatcher $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(
            'workflow.demo.guard.review',
            static function (GuardEvent $event) {
                $event->addTransitionBlocker(new TransitionBlocker(
                    'transitionIsBlocked',
                    'guardFail'
                ));
            }
        );

        $workflow = static::getContainer()->get('state_machine.demo');
        $list = WorkflowHelper::getTransitionList($subject, $workflow);

        self::assertSame([
            'review' => [
                'blockers' => [
                    'guardFail' => 'transitionIsBlocked',
                ],
            ],
        ], $list);
    }

    public function testGetTransitionWithBlockedEvent(): void
    {
        $subject = new TestEntity();
        $subject->varcharColumn = 'draft';

        /** @var EventDispatcher $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(
            'workflow.demo.guard.review',
            static function (GuardEvent $event) {
                $event->setBlocked(true, 'review failed');
            }
        );

        $workflow = static::getContainer()->get('state_machine.demo');
        $list = WorkflowHelper::getTransitionList($subject, $workflow);

        self::assertSame([
            'review' => [
                'blockers' => [
                    TransitionBlocker::UNKNOWN => 'review failed',
                ],
            ],
        ], $list);
    }
}
