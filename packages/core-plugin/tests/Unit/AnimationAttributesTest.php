<?php
/**
 * Entrance animation attribute contract tests.
 *
 * @package Blocky\Core\Tests
 */

declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\RenderContext;
use PHPUnit\Framework\TestCase;

/**
 * The animation prop is a closed set; anything invalid renders as no animation.
 */
final class AnimationAttributesTest extends TestCase {

	/**
	 * A valid preset emits trigger/speed defaults.
	 */
	public function testValidPresetEmitsDefaults(): void {
		$attrs = RenderContext::makeMinimal()->blockAttrs(
			new Node('n1', 'bky/heading', ['animation' => ['preset' => 'fade-up']]),
			['class' => 'existing']
		);

		$this->assertSame('fade-up', $attrs['data-bky-anim']);
		$this->assertSame('scroll', $attrs['data-bky-anim-trigger']);
		$this->assertSame('normal', $attrs['data-bky-anim-speed']);
		$this->assertArrayNotHasKey('data-bky-anim-delay', $attrs);
		$this->assertArrayNotHasKey('data-bky-anim-repeat', $attrs);
	}

	/**
	 * Unknown presets are dropped entirely.
	 */
	public function testUnknownPresetIsDropped(): void {
		$attrs = RenderContext::makeMinimal()->blockAttrs(
			new Node('n1', 'bky/heading', ['animation' => ['preset' => 'spin-like-crazy']]),
			[]
		);

		$this->assertArrayNotHasKey('data-bky-anim', $attrs);
	}

	/**
	 * Steps and booleans are normalized, off-step values fall back.
	 */
	public function testStepsAreNormalized(): void {
		$attrs = RenderContext::makeMinimal()->blockAttrs(
			new Node('n1', 'bky/heading', [
				'animation' => [
					'preset' => 'zoom-in',
					'trigger' => 'teleport',
					'speed' => 'hyperspeed',
					'delay' => 333,
					'repeat' => true,
				],
			]),
			[]
		);

		$this->assertSame('scroll', $attrs['data-bky-anim-trigger']);
		$this->assertSame('normal', $attrs['data-bky-anim-speed']);
		$this->assertArrayNotHasKey('data-bky-anim-delay', $attrs);
		$this->assertSame('1', $attrs['data-bky-anim-repeat']);
	}

	/**
	 * A known delay step is emitted verbatim.
	 */
	public function testKnownDelayStepEmitted(): void {
		$attrs = RenderContext::makeMinimal()->blockAttrs(
			new Node('n1', 'bky/heading', ['animation' => ['preset' => 'blur-in', 'delay' => 400]]),
			[]
		);

		$this->assertSame('400', $attrs['data-bky-anim-delay']);
	}

	/**
	 * Non-array animation props (legacy docs) are ignored.
	 */
	public function testNonArrayPropIgnored(): void {
		$attrs = RenderContext::makeMinimal()->blockAttrs(
			new Node('n1', 'bky/heading', ['animation' => 'fade-up']),
			[]
		);

		$this->assertArrayNotHasKey('data-bky-anim', $attrs);
	}
}
