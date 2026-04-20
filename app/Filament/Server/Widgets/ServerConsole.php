<?php

namespace App\Filament\Server\Widgets;

use App\Enums\SubuserPermission;
use App\Exceptions\Http\HttpForbiddenException;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Models\User;
use App\Services\Nodes\NodeJWTService;
use App\Services\Servers\GetUserPermissionsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;

class ServerConsole extends Widget
{
    protected string $view = 'filament.components.server-console';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    public ?User $user = null;

    /** @var string[] */
    #[Session(key: 'server.{server.id}.history')]
    public array $history = [];

    public int $historyIndex = 0;

    public string $input = '';

    private GetUserPermissionsService $getUserPermissionsService;

    private NodeJWTService $nodeJWTService;

    public function boot(GetUserPermissionsService $getUserPermissionsService, NodeJWTService $nodeJWTService): void
    {
        $this->getUserPermissionsService = $getUserPermissionsService;
        $this->nodeJWTService = $nodeJWTService;
    }

    protected function getToken(): string
    {
        if (!$this->user || !$this->server || $this->user->cannot(SubuserPermission::WebsocketConnect, $this->server)) {
            throw new HttpForbiddenException('You do not have permission to connect to this server\'s websocket.');
        }

        $permissions = $this->getUserPermissionsService->handle($this->server, $this->user);

        return $this->nodeJWTService
            ->setExpiresAt(now()->addMinutes(10)->toImmutable())
            ->setUser($this->user)
            ->setClaims([
                'server_uuid' => $this->server->uuid,
                'permissions' => $permissions,
            ])
            ->handle($this->server->node, $this->user->id . $this->server->uuid)->toString();
    }

    protected function getSocket(): string
    {
        $socket = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $this->server->node->getConnectionAddress());
        $socket .= sprintf('/api/servers/%s/ws', $this->server->uuid);

        return $socket;
    }

    protected function authorizeSendCommand(): bool
    {
        return $this->user->can(SubuserPermission::ControlConsole, $this->server);
    }

    protected function canSendCommand(): bool
    {
        return $this->authorizeSendCommand() && !$this->server->isInConflictState() && $this->server->retrieveStatus()->isStartingOrRunning();
    }

    public function up(): void
    {
        $this->historyIndex = min($this->historyIndex + 1, count($this->history) - 1);

        $this->input = $this->history[$this->historyIndex] ?? '';
    }

    public function down(): void
    {
        $this->historyIndex = max($this->historyIndex - 1, -1);

        $this->input = $this->history[$this->historyIndex] ?? '';
    }

    public function enter(): void
    {
        if (!empty($this->input) && $this->canSendCommand()) {
            $this->dispatch('sendServerCommand', command: $this->input);

            $this->history = Arr::prepend($this->history, $this->input);
            $this->historyIndex = -1;

            $this->input = '';
        }
    }

    #[On('token-request')]
    public function tokenRequest(): void
    {
        $this->dispatch('sendAuthRequest', token: $this->getToken());
    }

    #[On('store-stats')]
    public function storeStats(string $data): void
    {
        $data = json_decode($data);

        $timestamp = now()->getTimestamp();

        foreach ($data as $key => $value) {
            $cacheKey = "servers.{$this->server->id}.$key";
            $cachedStats = cache()->get($cacheKey, []);

            $cachedStats[$timestamp] = $value;

            cache()->put($cacheKey, array_slice($cachedStats, -120), now()->addMinute());
        }
    }

    #[On('websocket-error')]
    public function websocketError(): void
    {
        AlertBanner::make('websocket_error')
            ->title(trans('server/console.websocket_error.title'))
            ->body(trans('server/console.websocket_error.body'))
            ->danger()
            ->send();
    }

    public function getGamePanelType(): string
    {
        $egg = $this->server?->egg;
        if (!$egg) {
            return 'none';
        }

        $name     = strtolower($egg->name ?? '');
        $tags     = array_map('strtolower', $egg->tags ?? []);
        $features = array_map('strtolower', $egg->inherit_features ?? $egg->features ?? []);

        if (str_contains($name, 'rust') || in_array('rust', $tags)) {
            return 'rust';
        }

        if (str_contains($name, 'ark') || in_array('ark', $tags)) {
            return 'ark';
        }

        if (
            str_contains($name, 'minecraft') ||
            in_array('minecraft_java', $features) ||
            in_array('minecraft_bedrock', $features) ||
            in_array('minecraft', $tags)
        ) {
            return 'minecraft';
        }

        return 'none';
    }

    public function getGamePanelData(): array
    {
        return match ($this->getGamePanelType()) {
            'rust'      => $this->buildRustPanelData(),
            'ark'       => $this->buildArkPanelData(),
            'minecraft' => ['address' => $this->server?->allocation?->address ?? ''],
            default     => [],
        };
    }

    private function getServerVariable(string $name): ?string
    {
        $variable = $this->server?->variables?->firstWhere('env_variable', $name);
        if (!$variable) {
            return null;
        }
        $value = (string) ($variable->server_value ?? $variable->default_value ?? '');

        return $value !== '' ? $value : null;
    }

    private function buildRustPanelData(): array
    {
        $seed = $this->getServerVariable('WORLD_SEED') ?? '';
        $size = $this->getServerVariable('WORLD_SIZE') ?? '3500';

        if (!$seed) {
            return ['seed' => '', 'size' => $size, 'imageUrl' => null, 'pageUrl' => null];
        }

        $pageUrl  = "https://rustmaps.com/map/{$size}_{$seed}";
        $imageUrl = null;

        $apiKey = config('services.rustmaps.key');
        if ($apiKey) {
            $cacheKey = "rustmap.v4e.{$size}.{$seed}";
            $data     = cache()->remember($cacheKey, now()->addHours(24), function () use ($seed, $size, $apiKey) {
                $response = Http::withHeaders(['X-API-Key' => $apiKey])
                    ->timeout(8)
                    ->get("https://api.rustmaps.com/v4/maps/{$size}/{$seed}", [
                        'staging' => 'false',
                    ]);

                \Illuminate\Support\Facades\Log::info('Rustmaps API raw response', [
                    'status'  => $response->status(),
                    'headers' => $response->headers(),
                    'body'    => $response->body(),
                ]);

                if (!$response->ok()) {
                    return null;
                }

                return $response->json();
            });

            if ($data) {
                $payload  = $data['data'] ?? $data;
                $imageUrl = $payload['imageUrl'] ?? $payload['thumbnailUrl'] ?? null;
                $pageUrl  = $payload['url'] ?? $pageUrl;
            }
        }

        return compact('seed', 'size', 'imageUrl', 'pageUrl');
    }

    private function buildArkPanelData(): array
    {
        $mapVar = $this->getServerVariable('LEVEL') ?? $this->getServerVariable('MAP') ?? '';
        $key    = strtolower($mapVar);

        $maps = [
            'theisland'       => ['name' => 'The Island',      'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/346110/header.jpg'],
            'scorchedearth_p' => ['name' => 'Scorched Earth',  'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/375351/header.jpg'],
            'aberration_p'    => ['name' => 'Aberration',      'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/708770/header.jpg'],
            'extinction'      => ['name' => 'Extinction',      'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/887380/header.jpg'],
            'genesis'         => ['name' => 'Genesis: Part 1', 'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/1113660/header.jpg'],
            'genesis2'        => ['name' => 'Genesis: Part 2', 'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/1356540/header.jpg'],
            'crystalisles'    => ['name' => 'Crystal Isles',   'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/1100810/header.jpg'],
            'valguero_p'      => ['name' => 'Valguero',        'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/1184480/header.jpg'],
            'ragnarokv2'      => ['name' => 'Ragnarok',        'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/816670/header.jpg'],
            'ragnarok'        => ['name' => 'Ragnarok',        'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/816670/header.jpg'],
            'bobsmissions_wp' => ['name' => 'Lost Island',     'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/1887560/header.jpg'],
            'fjordur'         => ['name' => 'Fjordur',         'image' => 'https://cdn.akamai.steamstatic.com/steam/apps/1891780/header.jpg'],
        ];

        $matched = $maps[$key] ?? null;
        if (!$matched) {
            foreach ($maps as $mKey => $mData) {
                if ($key && (str_contains($key, $mKey) || str_contains($mKey, $key))) {
                    $matched = $mData;
                    break;
                }
            }
        }

        return $matched ?? ['name' => ucwords(str_replace('_', ' ', $mapVar)), 'image' => null];
    }
}
