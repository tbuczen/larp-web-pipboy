<?php

declare(strict_types=1);

namespace App\Tests\Support\Helper;

use App\Tests\Support\TestFixture;
use Codeception\Module;
use Codeception\Module\PhpBrowser;

final class InvitationBrowser extends Module
{
    public function openInvitation(string $fixtureName): void
    {
        // Keep single-use tokens out of Codeception's recorded scenario arguments.
        $browser = $this->getModule('PhpBrowser');
        assert($browser instanceof PhpBrowser);
        $browser->amOnPage('/invite/'.TestFixture::$invitationTokens[$fixtureName]);
    }

    public function rememberCreatedInvitation(string $fixtureName): void
    {
        $browser = $this->getModule('PhpBrowser');
        assert($browser instanceof PhpBrowser);
        $url = $browser->grabValueFrom('#invitation-link');
        if (!is_string($url) || preg_match('~/invite/([a-f0-9]{64})$~D', $url, $matches) !== 1) {
            throw new \UnexpectedValueException('The generated invitation link is invalid.');
        }
        TestFixture::$invitationTokens[$fixtureName] = $matches[1];
    }
}
