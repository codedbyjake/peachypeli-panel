<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;

class UModSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://umod.org';

    public function search(string $query): array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get(self::BASE_URL . '/plugins.json', [
            'search' => $query,
            'sort'   => 'downloads',
            'count'  => 20,
            'page'   => 1,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return $this->normalise($response->json('data', []));
    }

    public function getFeatured(): array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get(self::BASE_URL . '/plugins.json', [
            'sort'  => 'downloads',
            'count' => 20,
            'page'  => 1,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return $this->normalise($response->json('data', []));
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get(self::BASE_URL . "/plugins/{$pluginId}.json");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'version'      => $data['latest_release_version_formatted'] ?? $data['latest_release_version'] ?? 'unknown',
            'download_url' => $data['download_url'] ?? null,
        ];
    }

    public function getName(): string
    {
        return 'uMod';
    }

    public function getInstallDir(): string
    {
        return '/oxide/plugins';
    }

    public function getSlug(): string
    {
        return 'umod';
    }

    public function isArchive(): bool
    {
        return false;
    }

    /** @param array<int, array<string, mixed>> $raw */
    private function normalise(array $raw): array
    {
        return array_values(array_filter(array_map(function (array $p): ?array {
            $downloadUrl = $p['download_url'] ?? null;
            if (!$downloadUrl) {
                return null;
            }

            return [
                'id'           => $p['slug'] ?? $p['name'] ?? null,
                'name'         => $p['name'] ?? 'Unknown',
                'author'       => $p['author'] ?? 'Unknown',
                'description'  => $p['description'] ?? '',
                'version'      => $p['latest_release_version_formatted'] ?? $p['latest_release_version'] ?? 'unknown',
                'downloads'    => (int) ($p['downloads'] ?? 0),
                'icon_url'     => $p['icon_url'] ?? null,
                'url'          => isset($p['url']) ? self::BASE_URL . $p['url'] : null,
                'download_url' => $downloadUrl,
                'file_name'    => basename($downloadUrl),
            ];
        }, $raw)));
    }
}
