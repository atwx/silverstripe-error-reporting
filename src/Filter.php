<?php

namespace Atwx\ErrorReporting;

use Sentry\Event;
use Sentry\EventHint;
use SilverStripe\Control\HTTPResponse_Exception;
use Throwable;

/**
 * before_send filter: drops noise and client errors and sets a fingerprint per
 * cause, so the same bug with different ids, paths or call chains is one issue.
 */
final class Filter
{
    /** @var string[] regular expressions matched against the message */
    private $ignoreMessages;

    /** @var string[] exception classes (and subclasses) that are never reported */
    private $ignoreExceptions;

    /**
     * @param string[] $ignoreMessages
     * @param string[] $ignoreExceptions
     */
    public function __construct(array $ignoreMessages = [], array $ignoreExceptions = [])
    {
        $this->ignoreMessages = array_values(array_filter($ignoreMessages));
        $this->ignoreExceptions = array_values(array_filter($ignoreExceptions));
    }

    public function apply(Event $event, ?EventHint $hint = null): ?Event
    {
        $exception = $hint ? $hint->exception : null;
        if ($exception instanceof HTTPResponse_Exception
            && $exception->getResponse()->getStatusCode() < 500
        ) {
            return null;
        }
        foreach ($this->ignoreExceptions as $class) {
            if ($exception instanceof $class) {
                return null;
            }
        }
        $message = self::message($event, $exception);
        foreach ($this->ignoreMessages as $pattern) {
            if (@preg_match($pattern, $message)) {
                return null;
            }
        }
        $event->setFingerprint(self::fingerprint($event, $exception, $message));
        return $event;
    }

    /**
     * Exception type, normalized message and the innermost frame of the
     * project's own code (vendor code if there is none).
     *
     * @return string[]
     */
    public static function fingerprint(Event $event, ?Throwable $exception, string $message): array
    {
        $type = $exception ? get_class($exception) : 'message';
        $exceptions = $event->getExceptions();
        if ($exceptions) {
            $last = end($exceptions);
            $type = $last->getType();
            $value = $last->getValue();
            if ($value !== '') {
                $message = $value;
            }
        }
        return [$type, self::normalize($message), self::location($event)];
    }

    /**
     * Removes what differs between occurrences of one bug: numbers, ids, hashes,
     * absolute paths and quoted values, and Monolog's "Uncaught …" wrapper.
     */
    public static function normalize(string $message): string
    {
        $message = preg_replace('/^(Fatal Error \(E_\w+\): )?Uncaught (Exception )?[\w\\\\]+: /', '', $message);
        $message = preg_replace('#(/[\w.@-]+){2,}/?#', '<path>', $message);
        $message = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '<uuid>', $message);
        $message = preg_replace('/\b[0-9a-f]{16,}\b/i', '<hash>', $message);
        $message = preg_replace('/\d+/', 'N', $message);
        $message = preg_replace('/"[^"]{40,}"|\'[^\']{40,}\'/', '"…"', $message);
        $message = preg_replace('/\s+/', ' ', trim($message));
        return mb_substr($message, 0, 200);
    }

    /**
     * file:line of the innermost in-app frame of the last exception.
     */
    public static function location(Event $event): string
    {
        $exceptions = $event->getExceptions();
        if (!$exceptions) {
            return '';
        }
        $last = end($exceptions);
        $stacktrace = $last->getStacktrace();
        if (!$stacktrace) {
            return '';
        }
        $frames = array_reverse($stacktrace->getFrames());
        foreach ($frames as $frame) {
            if ($frame->isInApp()) {
                return $frame->getFile() . ':' . $frame->getLine();
            }
        }
        $top = $frames[0] ?? null;
        return $top ? $top->getFile() . ':' . $top->getLine() : '';
    }

    private static function message(Event $event, ?Throwable $exception): string
    {
        $parts = [(string) $event->getMessage()];
        foreach ($event->getExceptions() as $bag) {
            $parts[] = $bag->getType() . ': ' . $bag->getValue();
        }
        if ($exception) {
            $parts[] = get_class($exception) . ': ' . $exception->getMessage();
        }
        return implode("\n", array_filter($parts));
    }
}
