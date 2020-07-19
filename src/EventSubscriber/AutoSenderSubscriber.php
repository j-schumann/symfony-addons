<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;

/**
 * Adds a FROM address to every mail that has none set.
 * The address is configured in ENV|.env via MAILER_SENDER and injected in the
 * services.yaml definition.
 *
 * This replaces setting the sender via mailer.yaml as envelope
 * (@see https://symfonycasts.com/screencast/mailer/event-global-recipients)
 * as this would still require each mail to have a FROM address set and also
 * doesn't allow us to set a sender name.
 */
class AutoSenderSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $sender;

    public function __construct(string $sender)
    {
        $this->sender = $sender;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }

    public function onMessage(MessageEvent $event)
    {
        if ($event->isQueued()) {
            // nothing to do, mail will be queued by the symfony/mailer which
            // does not accept changing the message (cloned before).
            // But the message will need a FROM header to serialize for
            // symfony/messenger...
            return;
        }

        $message = $event->getMessage();
        if (!$message instanceof Message) {
            return;
        }

        if ($message->getHeaders()->has('From')) {
            return;
        }

        $message->getHeaders()->add(
            new MailboxListHeader('From', [
                Address::fromString($this->sender),
            ])
        );

        $event->setMessage($message);

        return $message;
    }
}
