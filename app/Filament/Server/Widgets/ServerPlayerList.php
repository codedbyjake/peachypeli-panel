<?php

namespace App\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class ServerPlayerList extends Widget
{
    protected string $view = 'filament.server.widgets.server-player-list';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    /** @var string[] */
    public array $players = [];

    public int $playerCount = 0;

    public int $maxPlayers = 0;

    public bool $available = false;

    public bool $loaded = false;

    public string $gameType = 'unknown';

    public function mount(): void
    {
        $this->gameType = $this->detectGameType();
    }

    private function detectGameType(): string
    {
        $egg = $this->server?->egg;
        if (!$egg) {
            return 'unknown';
        }

        $name = strtolower($egg->name ?? '');
        $features = array_map('strtolower', $egg->inherit_features ?? $egg->features ?? []);
        $tags = array_map('strtolower', $egg->tags ?? []);

        if (
            str_contains($name, 'minecraft') ||
            in_array('minecraft_java', $features) ||
            in_array('minecraft_bedrock', $features) ||
            in_array('minecraft', $tags)
        ) {
            return 'minecraft';
        }

        if (str_contains($name, 'rust') || in_array('rust', $tags)) {
            return 'rust';
        }

        if (str_contains($name, 'ark') || in_array('ark', $tags)) {
            return 'ark';
        }

        if (str_contains($name, 'valheim') || in_array('valheim', $tags)) {
            return 'valheim';
        }

        if (str_contains($name, 'palworld') || in_array('palworld', $tags)) {
            return 'palworld';
        }

        if (str_contains($name, 'fivem') || in_array('fivem', $tags) || in_array('gta', $tags)) {
            return 'fivem';
        }

        return 'unknown';
    }

    public function queryArkViaSourceQuery(): void
    {
        $host = $this->server?->allocation?->alias ?? $this->server?->allocation?->ip;

        $queryPort = null;
        foreach ($this->server?->variables ?? [] as $variable) {
            if ($variable->env_variable === 'QUERY_PORT') {
                $queryPort = (int) ($variable->server_value ?? $variable->default_value ?? 0);
                break;
            }
        }

        if (!$host || !$queryPort) {
            $this->available = false;
            $this->loaded = true;
            return;
        }

        $query = new \xPaw\SourceQuery\SourceQuery();
        try {
            $query->Connect($host, $queryPort, 3, \xPaw\SourceQuery\SourceQuery::SOURCE);
            $info       = $query->GetInfo();
            $rawPlayers = $query->GetPlayers();

            $this->maxPlayers  = (int) ($info['MaxPlayers'] ?? 0);
            $this->players     = array_values(array_filter(
                array_map(fn ($p) => ['name' => $p['Name'] ?? ''], $rawPlayers ?? []),
                fn ($p) => $p['name'] !== ''
            ));
            $this->playerCount = count($this->players);
            $this->available   = true;
            $this->loaded      = true;
        } catch (\Exception) {
            $this->available = false;
            $this->loaded    = true;
        } finally {
            $query->Disconnect();
        }
    }

    #[On('player-list-update')]
    public function receivePlayerList(array $players, int $count, int $max, bool $available): void
    {
        $this->players = array_values(array_filter($players));
        $this->playerCount = $count;
        $this->maxPlayers = $max;
        $this->available = $available;
        $this->loaded = true;
    }

    #[On('rcon-command-response')]
    public function receiveRconResponse(string $label, string $response): void
    {
        Notification::make()
            ->title($label)
            ->body($response ?: 'Command sent.')
            ->success()
            ->send();
    }

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState() && !$server->retrieveStatus()->isOffline();
    }
}
