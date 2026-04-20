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
        return $this->fetchFirst50();
    }

    public function getFeatured(): array
    {
        return $this->fetchFirst50();
    }

    /**
     * Fetch the first page of the community endpoint and return at most 50 packages.
     * Returns an empty array on any error — memory exhaustion, timeout, bad response.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFirst50(): array
    {
        try {
            $url      = self::BASE_URL . "/c/{$this->community}/api/v1/package/";
            $response = Http::timeout(10)->connectTimeout(5)->get($url);

            if (!$response->successful()) {
                Log::error('ThunderstoreSource: fetch failed', [
                    'community' => $this->community,
                    'status'    => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            if (!is_array($data)) {
                return [];
            }

            // Paginated DRF response: { results: [...] }
            $packages = array_key_exists('results', $data) ? ($data['results'] ?? []) : $data;

            return $this->normalise(array_slice($packages, 0, 50));
        } catch (\Throwable $e) {
            Log::error('ThunderstoreSource: fetch threw exception', [
                'community' => $this->community,
                'error'     => $e->getMessage(),
            ]);

            return [];
        }
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
