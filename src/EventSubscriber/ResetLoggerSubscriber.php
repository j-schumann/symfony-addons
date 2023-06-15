<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\EventSubscriber;

use Monolog\Handler\BufferHandler;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @todo how can we inject the handlers for all channels at once, so that we can
 * flush them all at once, without registering this subscriber multiple times?
 * @todo How can we reset the UidProcessor only once without depending on the
 * handler name?
 */
class ResetLoggerSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            // after a new message is received, before handling: reset the UID
            // added to the logs so we can separate log entries by handled
            // message, like with indivual HTTP requests
            WorkerMessageReceivedEvent::class => 'resetLogger',

            // after the handling succeeded or failed: flush all BufferHandlers
            // so we can immediatly see log entries, not just when the worker
            // exits / the buffer limit is reached
            WorkerMessageHandledEvent::class  => 'flushLogger',
            WorkerMessageFailedEvent::class   => 'flushLogger',
        ];
    }

    /**
     * For each received message, reset the UidProcessor to generate a new
     * identifier. But do this only once for the "app" channel, the same
     * processor is used for all handlers, we don't want to reset the ID twice
     * and possibly split messages between the IDs.
     */
    public function resetLogger()
    {
        if ('app' !== $this->logger->getName()) {
            return;
        }

        // don't simply reset() the logger itself, we don't need to flush
        // messages already
        foreach ($this->logger->getProcessors() as $processor) {
            if ($processor instanceof UidProcessor) {
                $processor->reset();
            }
        }
    }

    /**
     * After a message was processed flash any buffer handlers, we don't want
     * to wait till the next message to see the logs.
     */
    public function flushLogger()
    {
        // don't simply reset() the logger itself, this would cause the UID to
        // change too, which would separate log entries coming after this event
        // (low priority, but still belonging to this message, e.g. messenger ACK
        // to transport).
        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof BufferHandler) {
                $handler->flush();
            }
        }
    }
}
