<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Vrok\SymfonyAddons\EventSubscriber\AutoSenderSubscriber;

final class AutoSenderSubscriberTest extends TestCase
{
    public function testRegistersEvent(): void
    {
        $events = AutoSenderSubscriber::getSubscribedEvents();
        self::assertIsArray($events);
        self::assertArrayHasKey(MessageEvent::class, $events);
        self::assertSame('onMessage', $events[MessageEvent::class]);
    }

    public function testAddsSenderAddress(): void
    {
        $subscriber = new AutoSenderSubscriber('Sender <test@domain.tld>');
        $email = (new Email())
            ->to('receiver@domain.tld')
            ->subject('test')
            ->text('body');

        $event = new MessageEvent($email, Envelope::create($email), 'test');

        $subscriber->onMessage($event);

        self::assertNotNull($email->getFrom());
        $from = $email->getFrom();
        self::assertIsArray($from);
        self::assertCount(1, $from);
        self::assertSame('test@domain.tld', $from[0]->getAddress());
        self::assertSame('Sender', $from[0]->getName());
    }

    public function testKeepsExistingSender(): void
    {
        $subscriber = new AutoSenderSubscriber('Sender <test@domain.tld>');
        $email = (new Email())
            ->from('sender@domain.tld')
            ->to('receiver@domain.tld')
            ->subject('test')
            ->text('body');

        $event = new MessageEvent($email, Envelope::create($email), 'test');

        $subscriber->onMessage($event);

        self::assertNotNull($email->getFrom());
        $from = $email->getFrom();
        self::assertIsArray($from);
        self::assertCount(1, $from);
        self::assertSame('sender@domain.tld', $from[0]->getAddress());
        self::assertSame('', $from[0]->getName());
    }
}
