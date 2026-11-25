<?php

namespace Atwx\ErrorReporting;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Silverstripe 6 runs sake on symfony/console, which catches exceptions of a
 * command and only prints them, so they never reach Silverstripe's error
 * handler. This subscriber passes them to the default logger, from where the
 * error reporting handler sends them like any other uncaught exception.
 */
class ConsoleErrorSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::ERROR => 'onConsoleError'];
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $error = $event->getError();
        $command = $event->getCommand();
        try {
            Injector::inst()->get(LoggerInterface::class)->error(
                sprintf(
                    'Uncaught Exception %s: "%s" at %s line %s',
                    get_class($error),
                    $error->getMessage(),
                    $error->getFile(),
                    $error->getLine()
                ),
                [
                    'exception' => $error,
                    'command' => $command ? $command->getName() : null,
                ]
            );
        } catch (Throwable $e) {
            // reporting must never change how the command fails
        }
    }
}
