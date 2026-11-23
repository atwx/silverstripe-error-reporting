<?php

namespace Atwx\ErrorReporting;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Sentry\Monolog\Handler;
use Sentry\SentrySdk;
use SilverStripe\Core\Injector\Factory;

/**
 * Creates the Monolog handler that forwards errors and worse to Sentry, or a
 * NullHandler when reporting is not active.
 */
class HandlerFactory implements Factory
{
    public function create($service, array $params = []): ?object
    {
        if (!ErrorReporting::init()) {
            return new NullHandler();
        }
        return new Handler(SentrySdk::getCurrentHub(), Logger::ERROR, true, false);
    }
}
