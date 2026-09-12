<?php
declare(strict_types=1);

namespace Blocky\Core\Tokens;

/**
 * Resolves a token value through the brand/mode cascade:
 *   brand+mode → brand+light → default+light
 */
final class TokenResolver
{
    /**
     * @param array<string, array<string, mixed>> $tokenSets  Map of variant-id → flat token map
     */
    public function __construct(
        private readonly array $tokenSets
    ) {}

    /**
     * Resolve a token key for a specific brand and mode.
     *
     * @return mixed
     */
    public function resolve(string $tokenKey, string $brand, string $mode): mixed
    {
        $candidates = [
            "{$brand}--{$mode}",
            "{$brand}--light",
            "default--light",
        ];

        foreach ($candidates as $variantId) {
            $set = $this->tokenSets[$variantId] ?? null;
            if ($set !== null && array_key_exists($tokenKey, $set)) {
                return $set[$tokenKey];
            }
        }

        return null;
    }

    /**
     * Build a full token map for a brand+mode by cascading all layers.
     *
     * @return array<string, mixed>
     */
    public function resolveAll(string $brand, string $mode): array
    {
        $candidates = array_filter([
            "default--light",
            "{$brand}--light",
            $mode !== 'light' ? "{$brand}--{$mode}" : null,
        ]);

        $merged = [];
        foreach ($candidates as $variantId) {
            $set = $this->tokenSets[$variantId] ?? [];
            $merged = array_merge($merged, $set);
        }

        return $merged;
    }
}
