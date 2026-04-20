<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThunderstoreSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://thunderstore.io';

    // The community-scoped v1 endpoint returns only packages for that community but has no
    // pagination support and can be 150 MB+ for large communities (e.g. Valheim). We stream
    // the response and stop reading after MAX_STREAM_BYTES, then repair the truncated JSON
    // array before parsing. This keeps memory usage bounded without switching to the
    // cross-community experimental API that returns results from unrelated games.
    private const MAX_STREAM_BYTES = 6 * 1024 * 1024; // 6 MB ≈ 100–120 packages

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
        $packages = $this->streamFetchCommunity('search');

        if ($packages === null) {
            return [];
        }

        if ($query !== '') {
            $packages = array_values(array_filter($packages, function (array $p) use ($query): bool {
                $name = strtolower($p['name'] ?? '');
                $desc = strtolower($p['versions'][0]['description'] ?? '');

                return str_contains($name, $query) || str_contains($desc, $query);
            }));
        }

        usort($packages, fn ($a, $b) => ($b['total_downloads'] ?? 0) <=> ($a['total_downloads'] ?? 0));

        return $this->normalise(array_slice(array_values($packages), 0, 20));
    }

    public function getFeatured(): array
    {
        $packages = $this->streamFetchCommunity('getFeatured');

        if ($packages === null) {
            return [];
        }

        usort($packages, fn ($a, $b) => ($b['total_downloads'] ?? 0) <=> ($a['total_downloads'] ?? 0));

        return $this->normalise(array_slice($packages, 0, 20));
    }

    /**
     * Stream the community-scoped v1 API endpoint up to MAX_STREAM_BYTES, repair any
     * truncated JSON array, and return the decoded packages.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function streamFetchCommunity(string $caller): ?array
    {
        $url = self::BASE_URL . "/c/{$this->community}/api/v1/package/";

        $context = stream_context_create([
            'http' => ['timeout' => 30, 'ignore_errors' => true],
        ]);

        $handle = @fopen($url, 'r', false, $context);

        if ($handle === false) {
            Log::error("ThunderstoreSource {$caller}: failed to open stream", [
                'community' => $this->community,
                'url'       => $url,
            ]);

            return null;
        }

        $buffer = '';

        while (!feof($handle) && strlen($buffer) < self::MAX_STREAM_BYTES) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
        }

        fclose($handle);

        $buffer = ltrim($buffer);

        if ($buffer === '' || $buffer[0] !== '[') {
            Log::error("ThunderstoreSource {$caller}: unexpected response format", [
                'community' => $this->community,
                'preview'   => substr($buffer, 0, 200),
            ]);

            return null;
        }

        // If we stopped before the closing ']', repair the truncated array.
        // Top-level array elements end with '},' so find the last one and close the array.
        if (substr(rtrim($buffer), -1) !== ']') {
            $pos = strrpos($buffer, '},');
            if ($pos === false) {
                return null;
            }
            $buffer = substr($buffer, 0, $pos + 1) . ']';
        }

        $packages = json_decode($buffer, true);

        if (!is_array($packages)) {
            Log::error("ThunderstoreSource {$caller}: json_decode failed", [
                'community'  => $this->community,
                'json_error' => json_last_error_msg(),
            ]);

            return null;
        }

        Log::info("ThunderstoreSource {$caller}: loaded packages", [
            'community' => $this->community,
            'count'     => count($packages),
            'bytes_read' => strlen($buffer),
        ]);

        return $packages;
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
            $namespace     = $p['owner'] ?? $p['namespace'] ?? '';
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
                'url'          => self::BASE_URL . "/c/{$this->community}/p/{$namespace}/{$name}/",
                'download_url' => $latestVersion['download_url'] ?? null,
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
