<?php
/**
 * WpBlockConverter mapping contract tests.
 *
 * @package Blocky\Core\Tests
 */

declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Blocks\Registry;
use Blocky\Core\Support\PropsValidator;
use Blocky\Core\Support\WpBlockConverter;
use PHPUnit\Framework\TestCase;

/**
 * convertBlocks() is pure: parsed block arrays in, Blocky document out.
 */
final class WpBlockConverterTest extends TestCase {

	/**
	 * Helper: parsed-block fixture.
	 *
	 * @param  array<string, mixed> $attrs
	 * @param  array<int, array<string, mixed>> $inner
	 * @return array<string, mixed>
	 */
	private static function block(string $name, string $html = '', array $attrs = [], array $inner = []): array {
		return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => $html, 'innerContent' => [$html]];
	}

	public function testBasicsMapToEquivalentBlocks(): void {
		$doc = WpBlockConverter::convertBlocks([
			self::block('core/heading', '<h2>Hello <em>world</em></h2>', ['level' => 3]),
			self::block('core/paragraph', '<p>Plain text here.</p>'),
			self::block('core/image', '<figure></figure>', ['id' => 42, 'alt' => 'A cat', 'size' => 'full']),
			self::block('core/separator', '<hr/>'),
			self::block('core/spacer', '<div></div>', ['height' => 20]),
			self::block('core/list', '<ul><li>One</li><li>Two</li></ul>'),
			self::block('core/quote', '<p>Wisdom.</p><cite>Socrates</cite>'),
		], static fn(array $b): string => '<fallback/>');

		$types = array_map(static fn(array $n): string => $n['type'], array_diff_key($doc['nodes'], ['root' => 1]));
		$this->assertSame(
			['bky/heading', 'bky/text', 'bky/image', 'bky/divider', 'bky/spacer', 'bky/list', 'bky/quote'],
			array_values($types)
		);

		$heading = reset($doc['nodes']);
		foreach ($doc['nodes'] as $node) {
			if ($node['type'] === 'bky/heading') {
				$heading = $node;
			}
		}
		$this->assertSame('Hello world', $heading['props']['text']);
		$this->assertSame(3, $heading['props']['level']);

		foreach ($doc['nodes'] as $node) {
			if ($node['type'] === 'bky/image') {
				$this->assertSame(42, $node['props']['attachmentId']);
				$this->assertSame('A cat', $node['props']['alt']);
				$this->assertSame('large', $node['props']['size']);
			}
			if ($node['type'] === 'bky/spacer') {
				$this->assertSame('sm', $node['props']['size']);
			}
			if ($node['type'] === 'bky/list') {
				$this->assertSame("One\nTwo", $node['props']['items']);
				$this->assertFalse($node['props']['ordered']);
			}
			if ($node['type'] === 'bky/quote') {
				$this->assertSame('Socrates', $node['props']['citation']);
			}
		}
	}

	public function testColumnsBecomeColumnSlots(): void {
		$doc = WpBlockConverter::convertBlocks([
			self::block('core/columns', '', [], [
				self::block('core/column', '', [], [self::block('core/paragraph', '<p>Left</p>')]),
				self::block('core/column', '', [], [self::block('core/paragraph', '<p>Right</p>')]),
			]),
		], static fn(array $b): string => '<fallback/>');

		$columns = null;
		foreach ($doc['nodes'] as $node) {
			if ($node['type'] === 'bky/columns') {
				$columns = $node;
			}
		}
		$this->assertNotNull($columns);
		$this->assertSame(2, $columns['props']['count']);
		$this->assertCount(1, $columns['slots']['column-1']);
		$this->assertCount(1, $columns['slots']['column-2']);
		$this->assertCount(1, $doc['nodes']['root']['slots']['default']);
	}

	public function testUnmappableFallsBackToHtmlNode(): void {
		$doc = WpBlockConverter::convertBlocks([
			self::block('core/embed-youtube', '<figure>iframe</figure>'),
			self::block('core/image', '<figure></figure>', ['url' => 'https://x/y.png']),
		], static fn(array $b): string => '<rendered>' . $b['blockName'] . '</rendered>');

		$html = [];
		foreach ($doc['nodes'] as $node) {
			if ($node['type'] === 'bky/html') {
				$html[] = $node['props']['html'];
			}
		}
		$this->assertCount(2, $html);
		$this->assertStringContainsString('core/embed-youtube', $html[0]);
	}

	public function testEmptyContentYieldsEmptyRoot(): void {
		$doc = WpBlockConverter::convertBlocks([], static fn(array $b): string => '');
		$this->assertSame('root', $doc['root']);
		$this->assertSame([], $doc['nodes']['root']['slots']['default']);
		$this->assertCount(1, $doc['nodes']);
	}

	public function testConvertedDocumentPassesTheSaveValidator(): void {
		$doc = WpBlockConverter::convertBlocks([
			self::block('core/heading', '<h1>Title</h1>'),
			self::block('core/paragraph', '<p>Body</p>'),
			self::block('core/buttons', '', [], [self::block('core/button', 'Click', ['url' => 'https://x.test', 'target' => '_blank'])]),
			self::block('core/columns', '', [], [
				self::block('core/column', '', [], [self::block('core/list', '<ol><li>A</li></ol>')]),
				self::block('core/column', '', [], [self::block('core/spacer', '', ['height' => 100])]),
			]),
			self::block('core/quote', '<p>Quoted.</p>'),
		], static fn(array $b): string => '<div>x</div>');

		$registry = new Registry();
		$registry->registerCoreBlocks();
		$errors = (new PropsValidator($registry))->validate($doc);

		$this->assertSame([], $errors);
	}
}
