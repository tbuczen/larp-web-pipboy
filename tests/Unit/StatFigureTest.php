<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\PipBoy\StatFigure;
use PHPUnit\Framework\TestCase;

final class StatFigureTest extends TestCase
{
    public function testTheBuiltInFigureIsUsedWithoutAReplacementImage(): void
    {
        self::assertNull((new StatFigure(sys_get_temp_dir().'/no-such-public-dir'))->imagePath());
    }

    public function testAReplacementImageIsPreferredInSvgPngWebpOrder(): void
    {
        $directory = sys_get_temp_dir().'/stat-figure-'.bin2hex(random_bytes(4));
        mkdir($directory.'/images', recursive: true);
        touch($directory.'/images/stat-figure.webp');
        self::assertSame('images/stat-figure.webp', (new StatFigure($directory))->imagePath());

        touch($directory.'/images/stat-figure.png');
        self::assertSame('images/stat-figure.png', (new StatFigure($directory))->imagePath());

        array_map('unlink', glob($directory.'/images/*') ?: []);
        rmdir($directory.'/images');
        rmdir($directory);
    }
}
