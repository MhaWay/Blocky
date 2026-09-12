<?php
declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Compiler\TailwindBinary;
use PHPUnit\Framework\TestCase;

final class TailwindBinaryTest extends TestCase
{
    public function testChecksumTableMatchesKnownTargets(): void
    {
        foreach (['tailwindcss-linux-x64', 'tailwindcss-linux-arm64', 'tailwindcss-macos-arm64', 'tailwindcss-windows-x64.exe'] as $target) {
            $hash = TailwindBinary::expected_hash($target);
            self::assertNotNull($hash, $target . ' checksum missing');
            self::assertSame(1, preg_match('/^[0-9a-f]{64}$/', (string) $hash), $target . ' checksum malformed');
        }
    }

    public function testUnknownTargetHasNoChecksum(): void
    {
        self::assertNull(TailwindBinary::expected_hash('tailwindcss-riscv64'));
    }

    public function testDetectTargetReturnsKnownAssetOrNull(): void
    {
        $target = TailwindBinary::detect_target();
        if ($target === null) {
            $this->markTestSkipped('unsupported platform');
        }
        self::assertArrayHasKey((string) $target, [
            'tailwindcss-linux-x64'        => true,
            'tailwindcss-linux-arm64'      => true,
            'tailwindcss-linux-x64-musl'   => true,
            'tailwindcss-linux-arm64-musl' => true,
            'tailwindcss-macos-x64'        => true,
            'tailwindcss-macos-arm64'      => true,
            'tailwindcss-windows-x64.exe'  => true,
        ]);
    }

    public function testDownloadUrlPinsVersion(): void
    {
        self::assertStringContainsString('/v' . TailwindBinary::VERSION . '/', TailwindBinary::download_url('tailwindcss-linux-x64'));
    }

    public function testUnverifiedBinaryCannotCompile(): void
    {
        $dir = sys_get_temp_dir() . '/blocky-bin-test-' . uniqid();
        mkdir($dir, 0777, true);
        $binary = new TailwindBinary($dir, static fn(string $cmd, array &$out): int => 0);

        self::assertFalse($binary->is_verified('tailwindcss-linux-x64'));
        self::assertFalse($binary->compile('tailwindcss-linux-x64', '/tmp/nope.css', '/tmp/nope-out.css'));
    }

    public function testEnsureRejectsCorruptDownload(): void
    {
        $dir = sys_get_temp_dir() . '/blocky-bin-test-' . uniqid();
        $binary = new TailwindBinary($dir);
        $fetcher = static function (string $url, string $path): bool {
            file_put_contents($path, 'not-a-binary');
            return true;
        };
        self::assertFalse($binary->ensure('tailwindcss-linux-x64', $fetcher));
        self::assertFalse(is_file($binary->binary_path('tailwindcss-linux-x64')));
    }
}
