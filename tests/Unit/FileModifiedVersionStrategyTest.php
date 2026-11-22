<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Asset\FileModifiedVersionStrategy;
use PHPUnit\Framework\TestCase;

final class FileModifiedVersionStrategyTest extends TestCase
{
    public function testExistingFilesGetTheirModificationTime(): void
    {
        $directory = sys_get_temp_dir().'/asset-version-'.bin2hex(random_bytes(4));
        mkdir($directory.'/css', recursive: true);
        file_put_contents($directory.'/css/app.css', 'body{}');
        touch($directory.'/css/app.css', 1_700_000_000);
        $strategy = new FileModifiedVersionStrategy($directory);

        self::assertSame('css/app.css?v=1700000000', $strategy->applyVersion('css/app.css'));
        self::assertSame('/css/app.css?x=1&v=1700000000', $strategy->applyVersion('/css/app.css?x=1'));
        self::assertSame('css/missing.css', $strategy->applyVersion('css/missing.css'));

        unlink($directory.'/css/app.css');
        rmdir($directory.'/css');
        rmdir($directory);
    }
}
