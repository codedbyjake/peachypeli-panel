<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThunderstoreSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://thunderstore.io';

    // v1 /package/ returns the full catalogue (150 MB+ for large communities like Valheim).
    // The experimental API supports pagination — always use it instead.
    private const EXPERIMENTAL_URL = self::BASE_URL . '/api/experimental/package/';

    // Hard limit: reject any response body larger than this before attempting json_decode.
    private const MAX_RESPONSE_BYTES = 10 * 1024 * 1024; // 10 MB

    private const FEATURED_PAGE_SIZE = 20;
    private const SEARCH_PAGE_SIZE   = 100;

    private string $community;

    private string $installDir;

    public function __construct(string $community, string $installDir = '/BepInEx/plugins')
    {
        $this->community  = $community;
        $this->installDir = $installDir;
    }

    public function search(string $query): array
    {
        $query = strtolower(trim($query));

        // Fetch a bounded set ordered by popularity; filter client-side on name/description.
        $params = [
            'community_identifier' => $this->community,
            'ordering'             => '-total_downloads',
            'page_size'            => self::SEARCH_PAGE_SIZE,
        ];

        $packages = $this->fetchPage($params, 'search');
        if ($packages === null) {
            return [];
        }

        if ($query !== '') {
            $packages = array_values(array_filter($packages, function (array $p) use ($query): bool {
                $name = strtolower($p['name'] ?? '');
                $desc = strtolower($p['latest']['description'] ?? $p['versions'][0]['description'] ?? '');

                return str_contains($name, $query) || str_contains($desc, $query);
            }));
        }

        return $this->normalise(array_slice($packages, 0, 20));
    }

    public function getFeatured(): array
    {
        $params = [
            'community_identifier' => $this->community,
            'ordering'             => '-total_downloads',
            'page_size'            => self::FEATURED_PAGE_SIZE,
        ];

        $packages = $this->fetchPage($params, 'getFeatured');
        if ($packages === null) {
            return [];
        }

        return $this->normalise($packages);
    }

    /**
     * Fetch one page from the experimental API.
     * Returns the results array, or null on any failure (logged internally).
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchPage(array $params, string $caller): ?array
    {
        $response = Http::timeout(15)->connectTimeout(5)
            ->get(self::EXPERIMENTAL_URL, $params);

        if (!$response->successful()) {
            Log::error("ThunderstoreSource {$caller} failed", [
                'community' => $this->community,
                'status'    => $response->status(),
            ]);

            return null;
        }

        $size = strlen($response->body());
        if ($size > self::MAX_RESPONSE_BYTES) {
            Log::error("ThunderstoreSource {$caller}: response too large, refusing to parse", [
                'community' => $this->community,
                'bytes'     => $size,
                'limit'     => self::MAX_RESPONSE_BYTES,
            ]);

            return null;
        }

        $data = $response->json();

        // Experimental API returns {next, previous, results:[...]}.
        return $data['results'] ?? (is_array($data) ? $data : null);
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
            $latestVersion = $p['latest'] ?? ($p['versions'][0] ?? []);
            // Experimental API uses 'namespace'; v1 used 'owner' — accept both.
            $namespace     = $p['namespace'] ?? $p['owner'] ?? '';
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
                'url'          => $p['package_url'] ?? (self::BASE_URL . "/package/{$namespace}/{$name}/"),
                'download_url' => $latestVersion['download_url'] ?? null,
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
