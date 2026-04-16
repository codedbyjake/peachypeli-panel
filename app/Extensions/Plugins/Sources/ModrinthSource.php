<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;

class ModrinthSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://api.modrinth.com/v2';

    // Loaders to target for download selection (server-side Minecraft plugins).
    private const LOADERS = ['paper', 'spigot', 'purpur', 'bukkit', 'folia'];

    public function search(string $query): array
    {
        $response = Http::timeout(10)->connectTimeout(5)
            ->withHeader('User-Agent', 'PeachyPanel/1.0')
            ->get(self::BASE_URL . '/search', [
                'query'   => $query,
                'facets'  => json_encode([['project_type:plugin']]),
                'limit'   => 20,
                'index'   => 'downloads',
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $this->normalise($response->json('hits', []));
    }

    public function getFeatured(): array
    {
        $response = Http::timeout(10)->connectTimeout(5)
            ->withHeader('User-Agent', 'PeachyPanel/1.0')
            ->get(self::BASE_URL . '/search', [
                'facets' => json_encode([['project_type:plugin']]),
                'limit'  => 20,
                'index'  => 'downloads',
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $this->normalise($response->json('hits', []));
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        $response = Http::timeout(10)->connectTimeout(5)
            ->withHeader('User-Agent', 'PeachyPanel/1.0')
            ->get(self::BASE_URL . "/project/{$pluginId}/version", [
                'loaders' => json_encode(self::LOADERS),
                'limit'   => 1,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $versions = $response->json();
        if (empty($versions)) {
            return null;
        }

        $latest = $versions[0];
        $file   = $latest['files'][0] ?? null;

        if (!$file) {
            return null;
        }

        return [
            'version'      => $latest['version_number'] ?? 'unknown',
            'download_url' => $file['url'],
            'file_name'    => $file['filename'],
        ];
    }

    public function getName(): string
    {
        return 'Modrinth';
    }

    public function getInstallDir(): string
    {
        return '/plugins';
    }

    public function getSlug(): string
    {
        return 'modrinth';
    }

    public function isArchive(): bool
    {
        return false;
    }

    /** @param array<int, array<string, mixed>> $raw */
    private function normalise(array $raw): array
    {
        return array_values(array_map(function (array $p): array {
            return [
                'id'           => $p['project_id'] ?? $p['slug'] ?? null,
                'slug'         => $p['slug'] ?? null,
                'name'         => $p['title'] ?? 'Unknown',
                'author'       => $p['author'] ?? 'Unknown',
                'description'  => $p['description'] ?? '',
                'version'      => $p['latest_version'] ?? 'unknown',
                'downloads'    => (int) ($p['downloads'] ?? 0),
                'icon_url'     => $p['icon_url'] ?? null,
                'url'          => 'https://modrinth.com/plugin/' . ($p['slug'] ?? $p['project_id']),
                'download_url' => null, // resolved lazily via getLatestVersion()
                'file_name'    => null,
            ];
        }, $raw));
    }
}
