<?php

namespace App\Extensions\Plugins\Sources;

use App\Extensions\Plugins\PluginSourceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ThunderstoreSource implements PluginSourceInterface
{
    private const BASE_URL = 'https://thunderstore.io';

    /** Hard cap on bytes read from any single response — prevents OOM on large API payloads. */
    private const MAX_RESPONSE_BYTES = 2 * 1024 * 1024; // 2 MB

    private string $community;

    private string $installDir;

    public function __construct(string $community, string $installDir = '/BepInEx/plugins')
    {
        $this->community  = $community;
        $this->installDir = $installDir;
    }

    public function search(string $query): array
    {
        return $this->fetchPackages();
    }

    public function getFeatured(): array
    {
        return $this->fetchPackages();
    }

    /**
     * Attempt to return up to 50 packages using the smallest available endpoint.
     * Falls back through endpoints in order; returns [] on total failure.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchPackages(): array
    {
        // 1. Try the experimental frontend endpoint — returns one small page per request.
        $frontendUrl = self::BASE_URL . "/api/experimental/frontend/c/{$this->community}/packages/";
        $result      = $this->streamRead($frontendUrl, 'experimental-frontend');

        if ($result !== null) {
            $packages = $this->extractPackageList($result, 'experimental-frontend');
            if (!empty($packages)) {
                Log::info('ThunderstoreSource: using experimental-frontend packages', [
                    'community' => $this->community,
                    'count'     => count($packages),
                ]);

                return $this->normalise(array_slice($packages, 0, 50));
            }
        }

        // 2. Fall back to v1 endpoint with the same 2 MB stream cap.
        $v1Url  = self::BASE_URL . "/c/{$this->community}/api/v1/package/";
        $result = $this->streamRead($v1Url, 'v1');

        if ($result !== null) {
            $packages = $this->extractPackageList($result, 'v1');
            if (!empty($packages)) {
                Log::info('ThunderstoreSource: using v1 packages', [
                    'community' => $this->community,
                    'count'     => count($packages),
                ]);

                return $this->normalise(array_slice($packages, 0, 50));
            }
        }

        return [];
    }

    /**
     * Open a Guzzle stream to $url, read at most MAX_RESPONSE_BYTES, then json_decode.
     * Returns the decoded value (array|null) or null on any error.
     */
    private function streamRead(string $url, string $label): mixed
    {
        try {
            $client   = new Client(['timeout' => 15, 'connect_timeout' => 5]);
            $response = $client->get($url, ['stream' => true]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                Log::warning("ThunderstoreSource [{$label}]: non-2xx response", [
                    'community' => $this->community,
                    'url'       => $url,
                    'status'    => $status,
                ]);

                return null;
            }

            $body    = $response->getBody();
            $content = '';

            while (!$body->eof() && strlen($content) < self::MAX_RESPONSE_BYTES) {
                $chunk = $body->read(65536);
                if ($chunk === '') {
                    break;
                }
                $content .= $chunk;
            }

            $bytesRead = strlen($content);
            $truncated = !$body->eof(); // still data left after cap
            $body->close();

            Log::info("ThunderstoreSource [{$label}]: stream read", [
                'community'  => $this->community,
                'url'        => $url,
                'bytes_read' => $bytesRead,
                'truncated'  => $truncated,
            ]);

            $decoded = json_decode($content, true);

            if ($decoded === null) {
                Log::error("ThunderstoreSource [{$label}]: json_decode failed", [
                    'community'  => $this->community,
                    'json_error' => json_last_error_msg(),
                    'truncated'  => $truncated,
                    'preview'    => substr($content, 0, 300),
                ]);

                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::error("ThunderstoreSource [{$label}]: exception", [
                'community' => $this->community,
                'url'       => $url,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract the package array from a decoded response regardless of envelope format.
     * Logs the top-level keys and first item keys to aid debugging unknown formats.
     *
     * @param  mixed  $decoded
     * @return array<int, array<string, mixed>>
     */
    private function extractPackageList(mixed $decoded, string $label): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        // Log top-level structure for diagnostics
        $topKeys = is_array($decoded) && !array_is_list($decoded) ? array_keys($decoded) : ['(list)'];
        Log::debug("ThunderstoreSource [{$label}]: top-level keys", [
            'community' => $this->community,
            'keys'      => $topKeys,
        ]);

        // Paginated DRF-style: { "results": [...] }
        if (isset($decoded['results']) && is_array($decoded['results'])) {
            $list = $decoded['results'];
        }
        // Thunderstore experimental frontend: { "packages": [...] }
        elseif (isset($decoded['packages']) && is_array($decoded['packages'])) {
            $list = $decoded['packages'];
        }
        // Flat array
        elseif (array_is_list($decoded)) {
            $list = $decoded;
        } else {
            Log::warning("ThunderstoreSource [{$label}]: unrecognised response shape", [
                'community' => $this->community,
                'top_keys'  => $topKeys,
            ]);

            return [];
        }

        // Log first item keys so we can see the package schema in the log
        if (!empty($list[0]) && is_array($list[0])) {
            Log::debug("ThunderstoreSource [{$label}]: first item keys", [
                'community' => $this->community,
                'keys'      => array_keys($list[0]),
            ]);
        }

        return $list;
    }

    public function getLatestVersion(string $pluginId): ?array
    {
        [$namespace, $name] = array_pad(explode('-', $pluginId, 2), 2, '');

        try {
            $result = $this->streamRead(
                self::BASE_URL . "/c/{$this->community}/api/v1/package/{$namespace}/{$name}/",
                'getLatestVersion'
            );
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($result)) {
            return null;
        }

        $version = $result['latest'] ?? ($result['versions'][0] ?? null);

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
            // v1 PackageListing format
            $latestVersion = $p['versions'][0] ?? [];
            $namespace     = $p['owner'] ?? ($p['namespace'] ?? '');
            $name          = $p['name'] ?? 'Unknown';
            $pluginId      = "{$namespace}-{$name}";

            return [
                'id'           => $pluginId,
                'name'         => $name,
                'author'       => $namespace,
                'description'  => $latestVersion['description'] ?? ($p['description'] ?? ''),
                'version'      => $latestVersion['version_number'] ?? ($p['version_number'] ?? 'unknown'),
                'downloads'    => (int) ($p['total_downloads'] ?? ($p['downloads'] ?? 0)),
                'icon_url'     => $latestVersion['icon'] ?? ($p['icon'] ?? null),
                'url'          => $p['package_url'] ?? ($p['url'] ?? ''),
                'download_url' => $latestVersion['download_url'] ?? ($p['download_url'] ?? null),
                'file_name'    => "{$pluginId}.zip",
            ];
        }, $raw));
    }
}
