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
        $params = [];
        $q      = trim($query);
        if ($q !== '') {
            $params['q'] = $q;
        }

        return $this->fetchFrontend($params);
    }

    public function getFeatured(): array
    {
        return $this->fetchFrontend([]);
    }

    /**
     * Fetch one page from the experimental frontend endpoint.
     * Returns a CommunityPackageList: { packages: PackageCard[], has_more_pages: bool }
     *
     * @param  array<string, string>  $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchFrontend(array $params): array
    {
        try {
            $url      = self::BASE_URL . "/api/experimental/frontend/c/{$this->community}/packages/";
            $response = Http::timeout(15)->connectTimeout(5)->get($url, $params);

            if (!$response->successful()) {
                Log::error('ThunderstoreSource: frontend fetch failed', [
                    'community' => $this->community,
                    'status'    => $response->status(),
                ]);

                return [];
            }

            $data     = $response->json();
            $packages = $data['packages'] ?? null;

            if (!is_array($packages)) {
                Log::error('ThunderstoreSource: unexpected response — no packages key', [
                    'community' => $this->community,
                    'keys'      => is_array($data) ? array_keys($data) : gettype($data),
                ]);

                return [];
            }

            return $this->normalise($packages);
        } catch (\Throwable $e) {
            Log::error('ThunderstoreSource: fetch exception', [
                'community' => $this->community,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        // pluginId is "Namespace-Name" — resolve download URL via the v1 detail endpoint.
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

    /** @param array<int, array<string, mixed>> $raw */
    private function normalise(array $raw): array
    {
        return array_values(array_map(function (array $p): array {
            $namespace   = $p['namespace'] ?? ($p['team_name'] ?? '');
            $packageName = $p['package_name'] ?? 'Unknown';
            $pluginId    = "{$namespace}-{$packageName}";

            return [
                'id'          => $pluginId,
                'name'        => $packageName,
                'author'      => $p['team_name'] ?? $namespace,
                'description' => $p['description'] ?? '',
                'version'     => 'Latest',
                'downloads'   => (int) ($p['download_count'] ?? 0),
                'icon_url'    => $p['image_src'] ?? null,
                'url'         => self::BASE_URL . "/c/{$this->community}/p/{$namespace}/{$packageName}/",
                // download_url is null here — PluginManager::install() will call
                // getLatestVersion() to resolve it via the v1 detail endpoint.
                'download_url' => null,
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
