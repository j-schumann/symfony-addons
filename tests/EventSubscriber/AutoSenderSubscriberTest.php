<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Vrok\SymfonyAddons\EventSubscriber\AutoSenderSubscriber;

class AutoSenderSubscriberTest extends TestCase
{
    public function testRegistersEvent(): void
    {
        $events = AutoSenderSubscriber::getSubscribedEvents();
        $this->assertIsArray($events);
        $this->assertArrayHasKey(MessageEvent::class, $events);
        $this->assertSame('onMessage', $events[MessageEvent::class]);
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

        $this->assertNotNull($email->getFrom());
        $from = $email->getFrom();
        $this->assertIsArray($from);
        $this->assertCount(1, $from);
        $this->assertSame('test@domain.tld', $from[0]->getAddress());
        $this->assertSame('Sender', $from[0]->getName());
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

        $this->assertNotNull($email->getFrom());
        $from = $email->getFrom();
        $this->assertIsArray($from);
        $this->assertCount(1, $from);
        $this->assertSame('sender@domain.tld', $from[0]->getAddress());
        $this->assertSame('', $from[0]->getName());
    }
}
