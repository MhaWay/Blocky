<?php
/**
 * Translation catalog asset tests.
 *
 * @package Blocky\Core\Tests
 */

declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Shipped catalogs must be valid, loadable .mo files for both plugins.
 */
final class TranslationCatalogsTest extends TestCase {

	/**
	 * Locales shipped with the plugin.
	 *
	 * @return array<int, string>
	 */
	public static function locales(): array {
		return ['it_IT', 'de_DE', 'es_ES', 'fr_FR', 'pt_BR'];
	}

	public function testTemplateExists(): void {
		$path = dirname(__DIR__, 2) . '/languages/blocky.pot';
		$this->assertFileExists($path);
		$pot = (string) file_get_contents($path);
		$count = substr_count($pot, 'msgid "');
		$this->assertGreaterThan(300, $count, 'The POT should cover the whole string inventory.');
	}

	/**
	 * @return array<int, array<int, string>>
	 */
	public static function catalogProvider(): array {
		$out = [];
		foreach (self::locales() as $locale) {
			$out[] = ['core', $locale];
			$out[] = ['builder', $locale];
		}
		return $out;
	}

	/**
	 * @param string $plugin Plugin directory name inside packages/.
	 * @param string $locale Locale slug.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('catalogProvider')]
	public function testCatalogIsValidMo(string $plugin, string $locale): void {
		$dir = dirname(__DIR__, 3) . '/' . ('core' === $plugin ? 'core-plugin' : 'builder-plugin') . '/languages';
		$mo = $dir . '/blocky-' . $locale . '.mo';
		$this->assertFileExists($mo);

		$bytes = (string) file_get_contents($mo, false, null, 0, 28);
		$header = unpack('Vmagic/Vrev/Vcount', substr($bytes, 0, 12));
		if (!is_array($header)) {
			$this->fail('Unreadable .mo header');
		}
		$this->assertSame(0x950412de, (int) $header['magic']);
		$this->assertGreaterThan(200, (int) $header['count'], 'Catalog should carry the full string table.');
	}

	/**
	 * @return void
	 */
	public function testItalianCatalogContainsKnownLabels(): void {
		$mo = dirname(__DIR__, 3) . '/core-plugin/languages/blocky-it_IT.mo';
		$this->assertFileExists($mo);
		$bytes = (string) file_get_contents($mo);
		$this->assertStringContainsString('Salva', $bytes);
		$this->assertStringContainsString('Impostazioni', $bytes);
	}
}
