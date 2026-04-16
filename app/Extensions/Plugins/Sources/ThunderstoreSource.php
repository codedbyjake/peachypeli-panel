<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;

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
        // Thunderstore v1 API does not support server-side search; fetch top packages and filter.
        $response = Http::timeout(15)->connectTimeout(5)
            ->get(self::BASE_URL . "/c/{$this->community}/api/v1/package/");

        if (!$response->successful()) {
            return [];
        }

        $packages = $response->json();
        if (!is_array($packages)) {
            return [];
        }

        $query = strtolower(trim($query));

        $filtered = array_filter($packages, function (array $p) use ($query): bool {
            $name = strtolower($p['name'] ?? '');
            $desc = strtolower($p['versions'][0]['description'] ?? '');

            return str_contains($name, $query) || str_contains($desc, $query);
        });

        usort($filtered, fn ($a, $b) => ($b['total_downloads'] ?? 0) <=> ($a['total_downloads'] ?? 0));

        return $this->normalise(array_slice(array_values($filtered), 0, 20));
    }

    public function getFeatured(): array
    {
        $response = Http::timeout(15)->connectTimeout(5)
            ->get(self::BASE_URL . "/c/{$this->community}/api/v1/package/");

        if (!$response->successful()) {
            return [];
        }

        $packages = $response->json();
        if (!is_array($packages)) {
            return [];
        }

        // Sort by downloads descending and return top 20.
        usort($packages, fn ($a, $b) => ($b['total_downloads'] ?? 0) <=> ($a['total_downloads'] ?? 0));

        return $this->normalise(array_slice($packages, 0, 20));
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
                'url'          => $p['package_url'] ?? (self::BASE_URL . "/package/{$namespace}/{$name}/"),
                'download_url' => $latestVersion['download_url'] ?? null,
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
