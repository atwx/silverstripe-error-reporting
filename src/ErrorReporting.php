<?php

namespace Atwx\ErrorReporting;

use Composer\InstalledVersions;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\RequestIntegration;
use Sentry\State\Scope;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

use function Sentry\configureScope;
use function Sentry\init;

/**
 * Sets up the Sentry SDK once per request from SENTRY_DSN in the environment.
 * Without a DSN, or in an environment not listed in enabled_environments,
 * nothing is reported.
 */
class ErrorReporting
{
    use Configurable;

    /**
     * Environment types that report.
     *
     * @config
     * @var string[]
     */
    private static $enabled_environments = ['live', 'test'];

    /**
     * Messages that are never reported (regular expressions). Projects add to
     * this list in their config; a value of null removes a default.
     *
     * @config
     * @var array<string, string|null>
     */
    private static $ignore_messages = [
        'allowed_hosts' => '/Allowed hosts has not been set/',
        'invalid_session' => '/Invalid session ID/',
        'session_functions' => '/session_(start|write_close|regenerate_id|destroy)\(\)/',
        'session_file' => '/Could not read session file/',
        'deprecated' => '/(^|\W)(E_(USER_)?DEPRECATED|Deprecated:)/',
        'response_code' => '/http_response_code\(\)/',
    ];

    /**
     * Exception classes (with subclasses) that are never reported.
     *
     * @config
     * @var array<string, string|null>
     */
    private static $ignore_exceptions = [];

    /**
     * Extra tags for every event.
     *
     * @config
     * @var array<string, string>
     */
    private static $tags = [];

    /** @var bool|null */
    private static $active;

    public static function dsn(): string
    {
        return (string) (Environment::getEnv('SENTRY_DSN') ?: Environment::getEnv('SS_SENTRY_DSN'));
    }

    /**
     * Initializes the SDK on the first call; returns whether reporting is active.
     */
    public static function init(): bool
    {
        if (self::$active !== null) {
            return self::$active;
        }
        $dsn = self::dsn();
        $environment = Director::get_environment_type();
        self::$active = $dsn !== ''
            && in_array($environment, (array) static::config()->get('enabled_environments'), true);
        if (!self::$active) {
            return false;
        }

        $base = defined('BASE_PATH') ? (realpath(BASE_PATH) ?: BASE_PATH) : getcwd();
        $installation = Paths::installation($base);
        $filter = new Filter(
            array_values(array_filter((array) static::config()->get('ignore_messages'))),
            array_values(array_filter((array) static::config()->get('ignore_exceptions')))
        );

        init([
            'dsn' => $dsn,
            'environment' => $environment,
            'release' => Environment::getEnv('SENTRY_RELEASE') ?: Paths::release($base),
            'server_name' => gethostname() ?: null,
            'send_default_pii' => false,
            'max_request_body_size' => 'never',
            // Silverstripe's own error handler logs uncaught errors to Monolog; the
            // SDK's global error/exception listeners would report them a second time.
            'default_integrations' => false,
            'integrations' => [
                new RequestIntegration(),
                new EnvironmentIntegration(),
                new FrameContextifierIntegration(),
            ],
            // everything outside vendor/ is the project's own code; an include list
            // would override this exclusion in the SDK
            'in_app_exclude' => [$base . '/vendor'],
            'prefixes' => [$base],
            'before_send' => static function (Event $event, ?EventHint $hint) use ($filter): ?Event {
                return $filter->apply($event, $hint);
            },
        ]);

        $tags = array_merge([
            'site' => Paths::site($installation),
            'installation' => $installation,
            'framework' => self::frameworkVersion(),
        ], (array) static::config()->get('tags'));
        configureScope(static function (Scope $scope) use ($tags): void {
            $scope->setTags(array_filter(array_map('strval', $tags), 'strlen'));
        });

        return true;
    }

    private static function frameworkVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('silverstripe/framework')) {
            return (string) InstalledVersions::getPrettyVersion('silverstripe/framework');
        }
        return '';
    }
}
