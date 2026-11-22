<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Holodisk;
use App\Service\HolodiskQrCode;
use App\Service\HolodiskScanner;
use PHPUnit\Framework\TestCase;

final class HolodiskCodeTest extends TestCase
{
    public function testNewHolodisksGetUnambiguousGeneratedCodes(): void
    {
        $code = (new Holodisk())->getCode();

        self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{12}$/', $code);
        self::assertNotSame($code, (new Holodisk())->getCode());
    }

    public function testCodesAreNormalized(): void
    {
        self::assertSame('ABCD1234', (new Holodisk())->setCode(' abcd-12 34 ')->getCode());
    }

    public function testRegenerationReplacesTheCodeOnlyWhenRequested(): void
    {
        $holodisk = (new Holodisk())->setCode('KEEPTHIS');
        $holodisk->applyCodeRegeneration();
        self::assertSame('KEEPTHIS', $holodisk->getCode());

        $holodisk->setRegenerateCode(true);
        $holodisk->applyCodeRegeneration();
        self::assertNotSame('KEEPTHIS', $holodisk->getCode());
        self::assertFalse($holodisk->isRegenerateCode());
    }

    public function testScannedLinksYieldTheirCode(): void
    {
        self::assertSame('ABCD1234', HolodiskScanner::codeFrom('https://pipboy.larpilot.com/scan/ABCD1234'));
        self::assertSame('ABCD1234', HolodiskScanner::codeFrom('https://pipboy.larpilot.com/scan/ABCD1234/?utm=x'));
        self::assertSame('abcd-1234', HolodiskScanner::codeFrom('  abcd-1234 '));
    }

    public function testDisplayCodesAreGroupedInFours(): void
    {
        self::assertSame('ABCD-EFGH-JK', HolodiskQrCode::displayCode('ABCDEFGHJK'));
    }
}
