<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;

class UModSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://umod.org';

    private const SEARCH_URL = 'https://umod.org/plugins/search.json';

    public function search(string $query): array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get(self::SEARCH_URL, [
            'query'       => $query,
            'game_filter' => 'rust',
            'sort'        => 'downloads',
            'count'       => 20,
            'page'        => 1,
        ]);

        if (!$response->successful()) {
            \Illuminate\Support\Facades\Log::error('UModSource search failed', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            return [];
        }

        return $this->normalise($response->json('data', []));
    }

    public function getFeatured(): array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get(self::SEARCH_URL, [
            'game_filter' => 'rust',
            'sort'        => 'downloads',
            'count'       => 20,
            'page'        => 1,
        ]);

        if (!$response->successful()) {
            \Illuminate\Support\Facades\Log::error('UModSource getFeatured failed', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            return [];
        }

        return $this->normalise($response->json('data', []));
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        $response = Http::timeout(10)->connectTimeout(5)->get(self::BASE_URL . "/plugins/{$pluginId}.json");

        if (!$response->successful()) {
            \Illuminate\Support\Facades\Log::error('UModSource getLatestVersion failed', [
                'plugin_id' => $pluginId,
                'status'    => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        return [
            'version'      => $data['latest_release_version_formatted'] ?? $data['latest_release_version'] ?? 'unknown',
            'download_url' => $data['download_url'] ?? null,
            'file_name'    => basename($data['download_url'] ?? ''),
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
                // 'title' is the human-readable display name; 'name' is the technical/file name
                'name'         => $p['title'] ?? $p['name'] ?? 'Unknown',
                'author'       => $p['author'] ?? 'Unknown',
                'description'  => $p['description'] ?? '',
                'version'      => $p['latest_release_version_formatted'] ?? $p['latest_release_version'] ?? 'unknown',
                'downloads'    => (int) ($p['downloads'] ?? 0),
                'icon_url'     => $p['icon_url'] ?? null,
                'url'          => $p['url'] ?? null,  // already absolute
                'download_url' => $downloadUrl,
                'file_name'    => basename($downloadUrl),
            ];
        }, $raw)));
    }
}
