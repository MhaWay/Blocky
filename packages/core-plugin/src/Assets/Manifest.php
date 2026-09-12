<?php
declare(strict_types=1);

namespace Blocky\Core\Assets;

/**
 * Vite manifest reader.
 * Reads `manifest.json` produced by Vite's build to resolve hashed asset URLs.
 */
final class Manifest
{
    /** @var array<string, array{file: string, src?: string, isEntry?: bool, css?: string[], imports?: string[]}> */
    private array $entries;

    /** @var bool */
    private bool $loaded = false;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $assetBaseUrl,
    ) {}

    /**
     * Get the public URL for a given source entry.
     */
    public function url(string $entry): ?string
    {
        $this->load();
        $record = $this->entries[$entry] ?? null;
        return $record ? $this->assetBaseUrl . '/' . ltrim($record['file'], '/') : null;
    }

    /**
     * Get CSS files generated for a given JS entry.
     *
     * @return string[]
     */
    public function cssFor(string $entry): array
    {
        $this->load();
        $record = $this->entries[$entry] ?? null;
        if ($record === null) {
            return [];
        }
        return array_map(
            fn(string $f) => $this->assetBaseUrl . '/' . ltrim($f, '/'),
            $record['css'] ?? []
        );
    }

    /**
     * Check if the manifest is available (i.e. we're in production mode).
     */
    public function isAvailable(): bool
    {
        return file_exists($this->manifestPath);
    }

    /**
     * Load and cache the manifest.
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        if (!file_exists($this->manifestPath)) {
            $this->entries = [];
            return;
        }

        $raw = file_get_contents($this->manifestPath);
        if ($raw === false) {
            $this->entries = [];
            return;
        }

        /** @var array<string, array{file: string, src?: string, isEntry?: bool, css?: string[], imports?: string[]}>|null */
        $decoded = json_decode($raw, true);
        $this->entries = is_array($decoded) ? $decoded : [];
    }
}
