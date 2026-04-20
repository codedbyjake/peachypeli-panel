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
        $params = ['ordering' => 'most_downloaded'];

        $q = trim($query);
        if ($q !== '') {
            $params['q'] = $q;
        }

        $packages = $this->fetchPage($params);

        return $this->normalise(array_slice($packages, 0, 100));
    }

    public function getFeatured(): array
    {
        $packages = $this->fetchPage(['ordering' => 'most_downloaded']);

        return $this->normalise(array_slice($packages, 0, 100));
    }

    /**
     * Fetch a single page from the community endpoint and return its package array.
     * Handles both paginated { results: [...] } and flat [...] responses.
     *
     * @param  array<string, string>  $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchPage(array $params = []): array
    {
        $url      = self::BASE_URL . "/c/{$this->community}/api/v1/package/";
        $response = Http::timeout(15)->connectTimeout(5)->get($url, $params);

        if (!$response->successful()) {
            Log::error('ThunderstoreSource: fetch failed', [
                'community' => $this->community,
                'url'       => $url,
                'params'    => $params,
                'status'    => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();

        if (!is_array($data)) {
            Log::error('ThunderstoreSource: unexpected response format', [
                'community' => $this->community,
            ]);

            return [];
        }

        // Paginated DRF response: { next, previous, results: [...] }
        if (array_key_exists('results', $data)) {
            return $data['results'] ?? [];
        }

        // Flat array (non-paginated endpoint variant)
        return $data;
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

    /** @param array<int, array<string, mixed>> $raw */
    private function normalise(array $raw): array
    {
        return array_values(array_map(function (array $p): array {
            $latestVersion = $p['versions'][0] ?? [];
            $namespace     = $p['owner'] ?? '';
            $name          = $p['name'] ?? 'Unknown';
            $pluginId      = "{$namespace}-{$name}";

            return [
                'id'           => $pluginId,
                'name'         => $name,
                'author'       => $namespace,
                'description'  => $latestVersion['description'] ?? '',
                'version'      => $latestVersion['version_number'] ?? 'unknown',
                'downloads'    => (int) ($p['total_downloads'] ?? 0),
                'icon_url'     => $latestVersion['icon'] ?? null,
                'url'          => $p['package_url'] ?? '',
                'download_url' => $latestVersion['download_url'] ?? null,
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
