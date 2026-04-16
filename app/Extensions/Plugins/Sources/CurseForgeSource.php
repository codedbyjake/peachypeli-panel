<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;

class CurseForgeSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://api.curseforge.com/v1';

    // CurseForge game IDs
    private const GAME_IDS = [
        'ark' => 83374,
    ];

    private string $game;

    private int $gameId;

    private string $installDir;

    public function __construct(string $game, int $gameId, string $installDir = '/mods')
    {
        $this->game       = $game;
        $this->gameId     = $gameId;
        $this->installDir = $installDir;
    }

    public function search(string $query): array
    {
        $apiKey = config('services.curseforge.api_key');
        if (!$apiKey) {
            return [];
        }

        $response = Http::timeout(10)->connectTimeout(5)
            ->withHeader('x-api-key', $apiKey)
            ->get(self::BASE_URL . '/mods/search', [
                'gameId'       => $this->gameId,
                'searchFilter' => $query,
                'pageSize'     => 20,
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $this->normalise($response->json('data', []));
    }

    public function getFeatured(): array
    {
        $apiKey = config('services.curseforge.api_key');
        if (!$apiKey) {
            return [];
        }

        $response = Http::timeout(10)->connectTimeout(5)
            ->withHeader('x-api-key', $apiKey)
            ->get(self::BASE_URL . '/mods/search', [
                'gameId'    => $this->gameId,
                'sortField' => 6, // totalDownloads
                'sortOrder' => 'desc',
                'pageSize'  => 20,
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $this->normalise($response->json('data', []));
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        $apiKey = config('services.curseforge.api_key');
        if (!$apiKey) {
            return null;
        }

        $response = Http::timeout(10)->connectTimeout(5)
            ->withHeader('x-api-key', $apiKey)
            ->get(self::BASE_URL . "/mods/{$pluginId}/files", [
                'pageSize' => 1,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $files = $response->json('data', []);
        $file  = $files[0] ?? null;

        if (!$file) {
            return null;
        }

        return [
            'version'      => $file['displayName'] ?? $file['fileName'] ?? 'unknown',
            'download_url' => $file['downloadUrl'] ?? null,
            'file_name'    => $file['fileName'] ?? null,
        ];
    }

    public function getName(): string
    {
        return 'CurseForge';
    }

    public function getInstallDir(): string
    {
        return $this->installDir;
    }

    public function getSlug(): string
    {
        return 'curseforge';
    }

    public function isArchive(): bool
    {
        return false;
    }

    public function hasApiKey(): bool
    {
        return !empty(config('services.curseforge.api_key'));
    }

    /** @param array<int, array<string, mixed>> $raw */
    private function normalise(array $raw): array
    {
        return array_values(array_map(function (array $m): array {
            $latestFile = $m['latestFiles'][0] ?? null;

            return [
                'id'           => (string) ($m['id'] ?? ''),
                'name'         => $m['name'] ?? 'Unknown',
                'author'       => $m['authors'][0]['name'] ?? 'Unknown',
                'description'  => $m['summary'] ?? '',
                'version'      => $latestFile['displayName'] ?? 'unknown',
                'downloads'    => (int) ($m['downloadCount'] ?? 0),
                'icon_url'     => $m['logo']['thumbnailUrl'] ?? null,
                'url'          => $m['links']['websiteUrl'] ?? null,
                'download_url' => $latestFile['downloadUrl'] ?? null,
                'file_name'    => $latestFile['fileName'] ?? null,
            ];
        }, $raw));
    }
}
