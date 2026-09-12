<?php
/**
 * PropsValidator contract tests.
 *
 * @package Blocky\Core\Tests
 */

declare(strict_types=1);

namespace Blocky\Core\Tests\Unit;

use Blocky\Core\Blocks\BlockDefinition;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Blocks\Registry;
use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\PropsValidator;
use Blocky\Core\Support\RenderContext;
use PHPUnit\Framework\TestCase;

/**
 * The registry schema is the save contract; the validator enforces it.
 */
final class PropsValidatorTest extends TestCase {

	/**
	 * Registry with a single fake block exercising every rule.
	 *
	 * @return Registry Prepared registry.
	 */
	private function registry(): Registry {
		$renderer = new class() implements BlockRendererInterface {
			/**
			 * Unused stub.
			 *
			 * @param Node          $node Node.
			 * @param RenderContext $ctx  Context.
			 * @return HtmlString Never returned.
			 */
			public function render( Node $node, RenderContext $ctx ): HtmlString {
				throw new \LogicException( 'not rendered here' );
			}
		};

		$registry = new Registry();
		$registry->register(
			new BlockDefinition(
				'test/card',
				array(
					'properties' => array(
						'columns' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 12,
						),
						'size'    => array(
							'type' => 'string',
							'enum' => array( 'sm', 'md', 'lg' ),
						),
						'ratio'   => array(
							'type'    => 'number',
							'minimum' => 0.5,
							'maximum' => 5.0,
						),
						'eager'   => array( 'type' => 'boolean' ),
						'meta'    => array(
							'type'       => 'object',
							'properties' => array(
								'alt' => array( 'type' => 'string' ),
							),
						),
						'tags'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				array(),
				$renderer
			)
		);
		return $registry;
	}

	/**
	 * Builds a single-node document.
	 *
	 * @param array<string,mixed> $props Props payload.
	 * @return array<string,mixed> Document.
	 */
	private function doc( array $props ): array {
		return array(
			'root'  => 'n1',
			'nodes' => array(
				'n1' => array(
					'type'  => 'test/card',
					'props' => $props,
				),
			),
		);
	}

	/**
	 * Valid props produce no errors.
	 *
	 * @return void
	 */
	public function test_accepts_valid_props(): void {
		$validator = new PropsValidator( $this->registry() );
		$errors    = $validator->validate(
			$this->doc(
				array(
					'columns' => 3,
					'size'    => 'md',
					'ratio'   => 4.5,
					'eager'   => true,
					'meta'    => array( 'alt' => 'hi' ),
					'tags'    => array( 'a', 'b' ),
				)
			)
		);
		self::assertSame( array(), $errors );
	}

	/**
	 * Range, enum, type and unknown-type violations all report.
	 *
	 * @return void
	 */
	public function test_rejects_out_of_range_and_types(): void {
		$validator = new PropsValidator( $this->registry() );
		$errors    = $validator->validate(
			$this->doc(
				array(
					'columns' => 40,
					'size'    => 'xl',
					'ratio'   => 'wat',
					'eager'   => 'yes',
				)
			)
		);
		self::assertCount( 4, $errors );
		self::assertStringContainsString( 'above maximum 12', $errors[0] );
		self::assertStringContainsString( 'allowed set', $errors[1] );
		self::assertStringContainsString( 'number expected', $errors[2] );
		self::assertStringContainsString( 'boolean expected', $errors[3] );
	}

	/**
	 * Below minimum and integer violations report.
	 *
	 * @return void
	 */
	public function test_rejects_below_minimum_and_wrong_integer(): void {
		$validator = new PropsValidator( $this->registry() );
		$errors    = $validator->validate(
			$this->doc(
				array(
					'columns' => 1.5,
					'ratio'   => 0.1,
				)
			)
		);
		self::assertCount( 2, $errors );
		self::assertStringContainsString( 'integer expected', $errors[0] );
		self::assertStringContainsString( 'below minimum 0.5', $errors[1] );
	}

	/**
	 * Nested object and array item violations report with paths.
	 *
	 * @return void
	 */
	public function test_nested_paths_are_reported(): void {
		$validator = new PropsValidator( $this->registry() );
		$errors    = $validator->validate(
			$this->doc(
				array(
					'meta' => array( 'alt' => 123 ),
					'tags' => array( 'ok', 99 ),
				)
			)
		);
		self::assertCount( 2, $errors );
		self::assertStringContainsString( 'meta.alt', $errors[0] );
		self::assertStringContainsString( 'tags[]', $errors[1] );
	}

	/**
	 * Unknown block types are rejected; unknown extra props tolerated.
	 *
	 * @return void
	 */
	public function test_unknown_type_rejected_extra_props_tolerated(): void {
		$validator = new PropsValidator( $this->registry() );

		$errors = $validator->validate(
			array(
				'root'  => 'n1',
				'nodes' => array( 'n1' => array( 'type' => 'test/ghost', 'props' => array() ) ),
			)
		);
		self::assertCount( 1, $errors );
		self::assertStringContainsString( 'unknown block type', $errors[0] );

		$ok = $validator->validate( $this->doc( array( 'size' => 'sm', 'legacy_extra' => 'anything' ) ) );
		self::assertSame( array(), $ok );
	}
}

