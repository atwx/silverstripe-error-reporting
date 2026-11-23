<?php

namespace Atwx\ErrorReporting\Tests;

use Atwx\ErrorReporting\Paths;
use PHPUnit\Framework\TestCase;

class PathsTest extends TestCase
{
    public function testEnvoyerReleaseOnFroxlor(): void
    {
        $base = '/var/www/kunden/webs/kjr/www23/releases/20261123165310';
        $this->assertSame('/var/customers/webs/kjr/www23', Paths::installation($base));
        $this->assertSame('kjr/www23', Paths::site(Paths::installation($base)));
        $this->assertSame('20261123165310', Paths::release($base));
    }

    public function testPloiZeroDowntimeRelease(): void
    {
        $base = '/home/alliedvision/alliedvision.com-deploy/alliedvision.com/20261121180000';
        $this->assertSame('/home/alliedvision/alliedvision.com', Paths::installation($base));
        $this->assertSame('alliedvision.com', Paths::site(Paths::installation($base)));
        $this->assertSame('20261121180000', Paths::release($base));
    }

    public function testPlainCheckout(): void
    {
        $base = '/var/customers/webs/atwio/showtime';
        $this->assertSame($base, Paths::installation($base));
        $this->assertSame('atwio/showtime', Paths::site($base));
        $this->assertNull(Paths::release($base));
    }
}
