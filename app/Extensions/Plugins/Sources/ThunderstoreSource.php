<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThunderstoreSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://thunderstore.io';

    private string $community;

    private string $installDir;

    public function __construct(string $community, string $installDir = '/BepInEx/plugins')
    {
        $this->community  = $community;
        $this->installDir = $installDir;
    }

    public function search(string $query): array
    {
        $params = ['page' => 1];
        $q      = trim($query);
        if ($q !== '') {
            $params['q'] = $q;
        }

        return $this->fetchPage($params);
    }

    public function getFeatured(): array
    {
        return $this->fetchPage(['page' => 1]);
    }

    private function fetchPage(array $params): array
    {
        $url      = self::BASE_URL . "/api/experimental/frontend/c/{$this->community}/packages/";
        $response = Http::timeout(10)->connectTimeout(5)->get($url, $params);

        if (!$response->successful()) {
            Log::error('ThunderstoreSource: fetch failed', [
                'community' => $this->community,
                'status'    => $response->status(),
            ]);

            return [];
        }

        $packages = $response->json('packages', []);

        return $this->normalise($packages);
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        [$namespace, $name] = array_pad(explode('-', $pluginId, 2), 2, '');

        $response = Http::timeout(10)->connectTimeout(5)
            ->get(self::BASE_URL . "/c/{$this->community}/api/v1/package/{$namespace}/{$name}/");

        if (!$response->successful()) {
            return null;
        }

        $data    = $response->json();
        $version = $data['latest'] ?? ($data['versions'][0] ?? null);

        if (!$version) {
            return null;
        }

        return [
            'version'      => $version['version_number'] ?? 'unknown',
            'download_url' => $version['download_url'] ?? null,
            'file_name'    => "{$pluginId}.zip",
        ];
    }

    public function getName(): string
    {
        return 'Thunderstore (' . ucfirst($this->community) . ')';
    }

    public function getInstallDir(): string
    {
        return $this->installDir;
    }

    public function getSlug(): string
    {
        return 'thunderstore';
    }

    public function isArchive(): bool
    {
        return true;
    }

    private function normalise(array $raw): array
    {
        return array_values(array_map(function (array $p): array {
            $namespace   = $p['namespace'] ?? '';
            $packageName = $p['package_name'] ?? 'Unknown';
            $pluginId    = "{$namespace}-{$packageName}";

            return [
                'id'           => $pluginId,
                'name'         => $packageName,
                'author'       => $p['team_name'] ?? $namespace,
                'description'  => $p['description'] ?? '',
                'version'      => 'Latest',
                'downloads'    => (int) ($p['download_count'] ?? 0),
                'icon_url'     => $p['image_src'] ?? null,
                'url'          => self::BASE_URL . "/c/{$this->community}/p/{$namespace}/{$packageName}/",
                'download_url' => null,
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
