<?php

declare(strict_types=1);

namespace App\Tests\Support\Helper;

use Codeception\Module;
use Codeception\Module\PhpBrowser;

/** Response header assertions, which PhpBrowser does not provide. */
final class HttpHeaders extends Module
{
    public function seeResponseHeader(string $name, string $expected): void
    {
        $browser = $this->getModule('PhpBrowser');
        assert($browser instanceof PhpBrowser);
        $this->assertSame($expected, $browser->client->getInternalResponse()->getHeader($name));
    }
}
