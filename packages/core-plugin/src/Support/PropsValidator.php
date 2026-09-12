<?php
/**
 * Server-side props contract: every stored prop obeys the registry schema.
 *
 * This is the single validation path for builder saves, REST and future
 * MCP writes (AGENTS rule 3). Unknown extra props are tolerated so legacy
 * documents keep saving; declared props must match type, enum and range.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

use Blocky\Core\Blocks\Registry;

/**
 * Validates a BuilderDoc's node props against registry schemas.
 */
final class PropsValidator {

	/**
	 * Maximum number of human-readable errors collected per document.
	 *
	 * @var int
	 */
	private const MAX_ERRORS = 50;

	/**
	 * Block registry holding the property schemas.
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Registry $registry Block registry.
	 */
	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Validate every node in a document.
	 *
	 * @param array<string,mixed> $document Decoded BuilderDoc.
	 * @return list<string> Error messages, empty when valid.
	 */
	public function validate( array $document ): array {
		$errors = array();
		$nodes  = isset( $document['nodes'] ) && is_array( $document['nodes'] ) ? $document['nodes'] : array();

		foreach ( $nodes as $node_id => $node ) {
			if ( ! is_array( $node ) ) {
				$errors[] = 'node ' . $node_id . ': not an object';
				continue;
			}

			$this->collect_node_errors( (string) $node_id, $node, $errors );

			if ( count( $errors ) >= self::MAX_ERRORS ) {
				break;
			}
		}

		return array_slice( $errors, 0, self::MAX_ERRORS );
	}

	/**
	 * Validate one node entry.
	 *
	 * @param string              $node_id Node identifier for messages.
	 * @param array<string,mixed> $node    Raw node payload.
	 * @param array<int,string>   $errors  Error list, appended in place.
	 * @return void
	 */
	private function collect_node_errors( string $node_id, array $node, array &$errors ): void {
		$type = isset( $node['type'] ) && is_string( $node['type'] ) ? $node['type'] : '';

		if ( '' === $type || ! $this->registry->has( $type ) ) {
			$errors[] = 'node ' . $node_id . ': unknown block type "' . $type . '"';
			return;
		}

		$definition = $this->registry->get( $type );
		if ( null === $definition ) {
			return;
		}

		$schema = isset( $definition->schema['properties'] ) && is_array( $definition->schema['properties'] )
			? $definition->schema['properties']
			: array();
		$props  = isset( $node['props'] ) && is_array( $node['props'] ) ? $node['props'] : array();

		foreach ( $schema as $prop_name => $prop_schema ) {
			if ( ! is_array( $prop_schema ) || ! array_key_exists( $prop_name, $props ) ) {
				continue;
			}

			$this->collect_prop_errors( $node_id, $type, (string) $prop_name, $prop_schema, $props[ $prop_name ], $errors );
		}
	}

	/**
	 * Validate a single prop value against its declared schema.
	 *
	 * @param string              $node_id     Node identifier.
	 * @param string              $type        Block type.
	 * @param string              $prop_name   Property name.
	 * @param array<string,mixed> $prop_schema Declared schema fragment.
	 * @param mixed               $value       Submitted value.
	 * @param array<int,string>   $errors      Error list, appended in place.
	 * @return void
	 */
	private function collect_prop_errors( string $node_id, string $type, string $prop_name, array $prop_schema, $value, array &$errors ): void {
		$label = 'node ' . $node_id . ' (' . $type . ') prop "' . $prop_name . '"';

		if ( null === $value ) {
			return;
		}

		if ( array_key_exists( 'enum', $prop_schema ) && is_array( $prop_schema['enum'] ) ) {
			if ( ! in_array( $value, $prop_schema['enum'], true ) ) {
				$errors[] = $label . ': value not in allowed set';
			}
			return;
		}

		$declared = isset( $prop_schema['type'] ) && is_string( $prop_schema['type'] ) ? $prop_schema['type'] : '';

		if ( 'integer' === $declared ) {
			if ( ! is_int( $value ) ) {
				$errors[] = $label . ': integer expected';
				return;
			}
			$this->collect_range_errors( $label, $prop_schema, $value, $errors );
			return;
		}

		if ( 'number' === $declared ) {
			if ( ! is_numeric( $value ) || ( is_float( $value ) && ! is_finite( $value ) ) ) {
				$errors[] = $label . ': finite number expected';
				return;
			}
			$this->collect_range_errors( $label, $prop_schema, (float) $value, $errors );
			return;
		}

		if ( 'boolean' === $declared && ! is_bool( $value ) ) {
			$errors[] = $label . ': boolean expected';
			return;
		}

		if ( 'string' === $declared && ! is_string( $value ) ) {
			$errors[] = $label . ': string expected';
			return;
		}

		if ( 'array' === $declared ) {
			if ( ! is_array( $value ) ) {
				$errors[] = $label . ': array expected';
				return;
			}
			$items = isset( $prop_schema['items'] ) && is_array( $prop_schema['items'] ) ? $prop_schema['items'] : null;
			if ( null !== $items ) {
				foreach ( $value as $item ) {
					$this->collect_prop_errors( $node_id, $type, $prop_name . '[]', $items, $item, $errors );
				}
			}
			return;
		}

		if ( 'object' === $declared ) {
			if ( ! is_array( $value ) ) {
				$errors[] = $label . ': object expected';
				return;
			}
			$inner = isset( $prop_schema['properties'] ) && is_array( $prop_schema['properties'] ) ? $prop_schema['properties'] : array();
			foreach ( $inner as $inner_name => $inner_schema ) {
				if ( ! is_array( $inner_schema ) || ! array_key_exists( $inner_name, $value ) ) {
					continue;
				}
				$this->collect_prop_errors( $node_id, $type, $prop_name . '.' . $inner_name, $inner_schema, $value[ $inner_name ], $errors );
			}
		}
	}

	/**
	 * Enforce minimum/maximum bounds on a numeric value.
	 *
	 * @param string              $label        Message prefix.
	 * @param array<string,mixed> $prop_schema  Schema fragment with bounds.
	 * @param int|float           $value        Numeric value.
	 * @param array<int,string>   $errors       Error list, appended in place.
	 * @return void
	 */
	private function collect_range_errors( string $label, array $prop_schema, $value, array &$errors ): void {
		if ( array_key_exists( 'minimum', $prop_schema ) && is_numeric( $prop_schema['minimum'] ) && $value < $prop_schema['minimum'] ) {
			$errors[] = $label . ': below minimum ' . $prop_schema['minimum'];
		}

		if ( array_key_exists( 'maximum', $prop_schema ) && is_numeric( $prop_schema['maximum'] ) && $value > $prop_schema['maximum'] ) {
			$errors[] = $label . ': above maximum ' . $prop_schema['maximum'];
		}
	}
}
