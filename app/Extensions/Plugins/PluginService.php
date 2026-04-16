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

        if ($gameType === 'rust') {
            $framework  = $this->detectRustFramework($server);
            $installDir = $framework === 'carbon' ? '/carbon/plugins' : '/oxide/plugins';

            return new UModSource($framework, $installDir);
        }

        return match ($gameType) {
            'minecraft' => new ModrinthSource(),
            'valheim'   => new ThunderstoreSource('valheim', '/BepInEx/plugins'),
            'palworld'  => new ThunderstoreSource('palworld', '/Pal/Binaries/Win64/Mods'),
            'ark'       => new CurseForgeSource('ark', 83374, '/ShooterGame/Content/Mods'),
            default     => null,
        };
    }

    /**
     * Detect whether a Rust server is running Carbon or Oxide.
     *
     * Detection order (first match wins):
     *  1. Egg name contains "carbon"
     *  2. Server startup command contains "carbon"
     *  3. Egg startup_commands contain "carbon"
     *  4. A server/egg variable has an env_variable name that contains "CARBON"
     *     (e.g. CARBON_VERSION, CARBON_BUILD) — presence alone implies Carbon
     *  5. A variable named FRAMEWORK / MOD_FRAMEWORK / RUST_FRAMEWORK has value "carbon"
     *
     * Falls back to "oxide" when none match.
     */
    public function detectRustFramework(Server $server): string
    {
        // 1. Egg name
        $eggName = strtolower($server->egg?->name ?? '');
        if (str_contains($eggName, 'carbon')) {
            return 'carbon';
        }

        // 2. Server startup command (the per-server resolved startup string)
        if (str_contains(strtolower($server->startup ?? ''), 'carbon')) {
            return 'carbon';
        }

        // 3. Egg default startup_commands array
        foreach ($server->egg?->startup_commands ?? [] as $cmd) {
            if (str_contains(strtolower($cmd), 'carbon')) {
                return 'carbon';
            }
        }

        // 4 & 5. Server environment variables (EggVariable joined with server overrides)
        foreach ($server->variables as $variable) {
            $envName = strtolower($variable->env_variable ?? '');

            // Presence of any CARBON_* variable implies Carbon framework
            if (str_contains($envName, 'carbon')) {
                return 'carbon';
            }

            // Explicit framework selector variable
            if (in_array($envName, ['framework', 'mod_framework', 'rust_framework', 'oxide_framework'])) {
                $effectiveValue = strtolower(
                    (string) ($variable->server_value ?? $variable->default_value ?? '')
                );
                if (str_contains($effectiveValue, 'carbon')) {
                    return 'carbon';
                }
            }
        }

        return 'oxide';
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
