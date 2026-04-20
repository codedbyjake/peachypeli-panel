<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThunderstoreSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://thunderstore.io';

    private const CACHE_TTL = 3600; // 1 hour

    private string $community;

    private string $installDir;

    public function __construct(string $community, string $installDir = '/BepInEx/plugins')
    {
        $this->community  = $community;
        $this->installDir = $installDir;
    }

    public function search(string $query): array
    {
        $query    = strtolower(trim($query));
        $packages = $this->fetchAllPackages();

        if ($query !== '') {
            $packages = array_values(array_filter($packages, function (array $p) use ($query): bool {
                $name = strtolower($p['name'] ?? '');
                $desc = strtolower($p['versions'][0]['description'] ?? '');

                return str_contains($name, $query) || str_contains($desc, $query);
            }));
        }

        usort($packages, fn ($a, $b) => ($b['total_downloads'] ?? 0) <=> ($a['total_downloads'] ?? 0));

        return $this->normalise(array_values($packages));
    }

    public function getFeatured(): array
    {
        $packages = $this->fetchAllPackages();

        usort($packages, fn ($a, $b) => ($b['total_downloads'] ?? 0) <=> ($a['total_downloads'] ?? 0));

        return $this->normalise($packages);
    }

    /**
     * Return all packages for the community, using a 1-hour cache to avoid
     * re-fetching all pages on every request.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPackages(): array
    {
        return Cache::remember(
            'thunderstore_packages_' . $this->community,
            self::CACHE_TTL,
            fn () => $this->fetchAllPages()
        );
    }

    /**
     * Follow the paginated v1 community endpoint until `next` is null,
     * accumulating all packages into a single array.
     *
     * The endpoint may return either:
     *   - A paginated object: { next: string|null, results: [...] }
     *   - A flat array: [...]  (older/smaller communities)
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPages(): array
    {
        $url  = self::BASE_URL . "/c/{$this->community}/api/v1/package/";
        $all  = [];
        $page = 1;

        while ($url !== null) {
            $response = Http::timeout(30)->connectTimeout(5)->get($url);

            if (!$response->successful()) {
                Log::error('ThunderstoreSource: page fetch failed', [
                    'community' => $this->community,
                    'url'       => $url,
                    'status'    => $response->status(),
                ]);
                break;
            }

            $data = $response->json();

            if (!is_array($data)) {
                Log::error('ThunderstoreSource: unexpected response', [
                    'community' => $this->community,
                    'url'       => $url,
                ]);
                break;
            }

            if (array_key_exists('results', $data)) {
                // Paginated DRF response: { next, previous, results }
                $all  = array_merge($all, $data['results'] ?? []);
                $next = $data['next'] ?? null;
                $url  = is_string($next) ? $next : null;
            } else {
                // Flat array — entire catalogue in one response
                $all = $data;
                $url = null;
            }

            Log::info('ThunderstoreSource: fetched page', [
                'community'    => $this->community,
                'page'         => $page,
                'batch_count'  => count($data['results'] ?? $data),
                'running_total' => count($all),
            ]);

            $page++;
        }

        return $all;
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        // pluginId is "Namespace-Name"
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
