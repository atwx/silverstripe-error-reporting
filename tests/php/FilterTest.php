<?php

namespace Atwx\ErrorReporting\Tests;

use Atwx\ErrorReporting\ErrorReporting;
use Atwx\ErrorReporting\Filter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use SilverStripe\Control\HTTPResponse_Exception;
use TypeError;

class FilterTest extends TestCase
{
    private function filter(): Filter
    {
        $defaults = (new \ReflectionClass(ErrorReporting::class))->getDefaultProperties();
        return new Filter(array_values($defaults['ignore_messages']));
    }

    private function event(\Throwable $e, array $frames = []): array
    {
        $event = Event::createEvent();
        $event->setMessage('Uncaught Exception ' . get_class($e) . ': "' . $e->getMessage() . '"');
        $event->setExceptions([new ExceptionDataBag($e, $frames ? new Stacktrace($frames) : null)]);
        return [$event, EventHint::fromArray(['exception' => $e])];
    }

    public function testClientErrorsAreDropped(): void
    {
        [$event, $hint] = $this->event(new HTTPResponse_Exception('Not found', 404));
        $this->assertNull($this->filter()->apply($event, $hint));
    }

    public function testServerErrorsAreKept(): void
    {
        [$event, $hint] = $this->event(new HTTPResponse_Exception('Broken', 503));
        $this->assertNotNull($this->filter()->apply($event, $hint));
    }

    public function testNoiseIsDropped(): void
    {
        [$event, $hint] = $this->event(new RuntimeException('Invalid session ID'));
        $this->assertNull($this->filter()->apply($event, $hint));
        [$event, $hint] = $this->event(new RuntimeException('Allowed hosts has not been set. Your application …'));
        $this->assertNull($this->filter()->apply($event, $hint));
    }

    public function testSameCauseHasSameFingerprint(): void
    {
        $frames = static function (int $line): array {
            return [
                new Frame(null, 'vendor/silverstripe/framework/src/ORM/FieldType/DBField.php', 410, null, null, [], false),
                new Frame('render', 'app/src/Pages/RegistrationPage.php', $line, null, null, [], true),
                new Frame('XML', 'vendor/silverstripe/framework/src/ORM/FieldType/DBField.php', 270, null, null, [], false),
            ];
        };
        $message = 'SilverStripe\ORM\FieldType\DBField::XML(): Return value must be of type string, array returned';
        [$a, $hintA] = $this->event(new TypeError($message . ' (id 4711)'), $frames(88));
        [$b, $hintB] = $this->event(new TypeError($message . ' (id 815)'), $frames(88));
        [$c, $hintC] = $this->event(new TypeError($message . ' (id 815)'), $frames(120));

        $fa = $this->filter()->apply($a, $hintA)->getFingerprint();
        $this->assertSame($fa, $this->filter()->apply($b, $hintB)->getFingerprint());
        $this->assertNotSame($fa, $this->filter()->apply($c, $hintC)->getFingerprint());
        $this->assertSame('app/src/Pages/RegistrationPage.php:88', $fa[2]);
    }

    public function testNormalize(): void
    {
        $this->assertSame(
            'Failed to parse time string (N\') at position N (\'): Unexpected character',
            Filter::normalize('Failed to parse time string (2026\') at position 4 (\'): Unexpected character')
        );
        $this->assertSame(
            'Could not read session file: Failed to read file "<path>"',
            Filter::normalize('Could not read session file: Failed to read file "/home/a/.php-sessions/sess_x"')
        );
    }
}
