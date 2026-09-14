<?php
/**
 * @package Blocky\Core\Blocks
 */

declare(strict_types=1);

namespace Blocky\Core\Blocks;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * A single node in the BuilderDoc tree.
 */
final class Node
{
    /**
     * @param string               $id       Unique node ID
     * @param string               $type     Block type slug
     * @param array<string, mixed> $props    Block props (validated against schema)
     * @param array<string, string[]> $slots Named child node ID arrays
     * @param array<string, string>   $variants Active variant selections
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array  $props    = [],
        public readonly array  $slots    = [],
        public readonly array  $variants = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // Normalise the two supported child formats:
        // 1. slots: { "default": ["id1", "id2"] }  — named slot map (canonical)
        // 2. children: ["id1", "id2"]               — flat default-slot shorthand
        $slots = (array) ($data['slots'] ?? []);
        if (empty($slots) && isset($data['children']) && is_array($data['children'])) {
            $slots = ['default' => array_values($data['children'])];
        }

        return new self(
            id:       (string) ($data['id']       ?? ''),
            type:     (string) ($data['type']      ?? ''),
            props:    (array)  ($data['props']     ?? []),
            slots:    $slots,
            variants: (array)  ($data['variants']  ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->type,
            'props'    => $this->props,
            'slots'    => $this->slots,
            'variants' => $this->variants,
        ];
    }
}
