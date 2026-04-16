<?php

namespace App\Extensions\Plugins;

interface PluginSourceInterface
{
    /**
     * Search for plugins by query string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array;

    /**
     * Get featured/popular plugins for browsing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFeatured(): array;

    /**
     * Get the latest version info for a plugin.
     *
     * @return array{version: string, download_url: string}|null
     */
    public function getLatestVersion(string $pluginId): ?array;

    /** Human-readable name of this source (e.g. "uMod", "Modrinth"). */
    public function getName(): string;

    /** Default install directory on the server (e.g. "/oxide/plugins"). */
    public function getInstallDir(): string;

    /** Source identifier slug (e.g. "umod", "modrinth"). */
    public function getSlug(): string;

    /** Whether plugin archives (zip files) need to be extracted after download. */
    public function isArchive(): bool;
}
