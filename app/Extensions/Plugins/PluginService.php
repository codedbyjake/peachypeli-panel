<?php

namespace App\Extensions\Plugins;

use App\Extensions\Plugins\Sources\CurseForgeSource;
use App\Extensions\Plugins\Sources\ModrinthSource;
use App\Extensions\Plugins\Sources\ThunderstoreSource;
use App\Extensions\Plugins\Sources\UModSource;
use App\Models\Server;

class PluginService
{
    /**
     * Resolve the correct plugin source for the given server based on its egg.
     * Returns null if the game is not supported.
     */
    public function resolveSource(Server $server): ?PluginSourceInterface
    {
        $gameType = $this->detectGameType($server);

        return match ($gameType) {
            'rust'     => new UModSource(),
            'minecraft' => new ModrinthSource(),
            'valheim'  => new ThunderstoreSource('valheim', '/BepInEx/plugins'),
            'palworld' => new ThunderstoreSource('palworld', '/Pal/Binaries/Win64/Mods'),
            'ark'      => new CurseForgeSource('ark', 83374, '/ShooterGame/Content/Mods'),
            default    => null,
        };
    }

    /**
     * Detect the game type from the server's egg metadata.
     */
    public function detectGameType(Server $server): string
    {
        $egg = $server->egg;
        if (!$egg) {
            return 'unknown';
        }

        $name     = strtolower($egg->name ?? '');
        $features = array_map('strtolower', $egg->inherit_features ?? $egg->features ?? []);
        $tags     = array_map('strtolower', $egg->tags ?? []);

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

        if (str_contains($name, 'valheim') || in_array('valheim', $tags)) {
            return 'valheim';
        }

        if (str_contains($name, 'palworld') || in_array('palworld', $tags)) {
            return 'palworld';
        }

        if (str_contains($name, 'ark') || in_array('ark', $tags) || in_array('ark survival evolved', $tags)) {
            return 'ark';
        }

        return 'unknown';
    }

    /**
     * List of all supported game slugs.
     *
     * @return string[]
     */
    public function getSupportedGames(): array
    {
        return ['rust', 'minecraft', 'valheim', 'palworld', 'ark'];
    }
}
