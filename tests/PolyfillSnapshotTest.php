<?php

declare(strict_types=1);

namespace Javanile\Imap2\Tests;

use Javanile\Imap2\Polyfill;
use PHPUnit\Framework\TestCase;

/**
 * Pins Polyfill header/address parsing output so mail-mime-parser upgrades cannot change it silently.
 * Regenerate with UPDATE_POLYFILL_SNAPSHOTS=1 only when a change is intended.
 */
class PolyfillSnapshotTest extends TestCase
{
    private const SNAPSHOT_FILE = __DIR__ . '/fixtures/polyfill-snapshots.json';
    private const DEFAULT_HOST = 'default.host';

    private const ADDRESS_LISTS = [
        'Sender <sender@test.com>, CC@test.com, root',
        '"Doe, John" <john.doe@example.com>',
        '=?UTF-8?B?w4lsb2RpZQ==?= <elodie@example.fr>',
        'a@b.c (Comment), <d@e.f>',
        'Group: x@y.z, w@v.u;',
        'nohost',
        '"Quoted \"Name\"" <q@x.y>',
        'Ünïcödé <u@x.y>',
    ];

    private static array $snapshots = [];
    private static bool $updated = false;

    public static function setUpBeforeClass(): void
    {
        self::$snapshots = is_file(self::SNAPSHOT_FILE)
            ? json_decode(file_get_contents(self::SNAPSHOT_FILE), true, 512, JSON_THROW_ON_ERROR)
            : [];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$updated) {
            ksort(self::$snapshots);
            file_put_contents(
                self::SNAPSHOT_FILE,
                json_encode(self::$snapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
            );
        }
    }

    public function headerFixtureProvider(): array
    {
        $cases = [];
        foreach (glob(__DIR__ . '/fixtures/*.eml') as $path) {
            $cases[basename($path)] = [basename($path)];
        }

        return $cases;
    }

    /**
     * @dataProvider headerFixtureProvider
     */
    public function testRfc822ParseHeadersMatchesSnapshot(string $fixture): void
    {
        $message = file_get_contents(__DIR__ . '/fixtures/' . $fixture);

        $this->assertMatchesSnapshot(
            'headers:' . $fixture,
            fn () => Polyfill::rfc822ParseHeaders($message, self::DEFAULT_HOST)
        );
    }

    public function addressListProvider(): array
    {
        return array_combine(self::ADDRESS_LISTS, array_map(fn ($list) => [$list], self::ADDRESS_LISTS));
    }

    /**
     * @dataProvider addressListProvider
     */
    public function testRfc822ParseAdrListMatchesSnapshot(string $addressList): void
    {
        $this->assertMatchesSnapshot(
            'adrlist:' . $addressList,
            fn () => Polyfill::rfc822ParseAdrList($addressList, self::DEFAULT_HOST)
        );
    }

    private function assertMatchesSnapshot(string $key, callable $parse): void
    {
        try {
            $actual = json_decode(json_encode($parse(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $actual = ['error' => get_class($e)];
        }

        if (getenv('UPDATE_POLYFILL_SNAPSHOTS')) {
            self::$snapshots[$key] = $actual;
            self::$updated = true;
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertArrayHasKey($key, self::$snapshots, "No snapshot for {$key}; run with UPDATE_POLYFILL_SNAPSHOTS=1.");
        $this->assertSame(self::$snapshots[$key], $actual);
    }
}
