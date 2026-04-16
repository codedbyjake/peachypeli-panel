<?php

namespace App\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Widgets\Widget;

class ServerConnectionInfo extends Widget
{
    protected string $view = 'filament.server.widgets.server-connection-info';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    public function getAddress(): string
    {
        return $this->server?->allocation?->address ?? 'N/A';
    }

    public function getSteamConnectUrl(): string
    {
        return 'steam://connect/' . $this->getAddress();
    }

    public function getNodeName(): string
    {
        return $this->server?->node?->name ?? 'Unknown';
    }

    public function getGameName(): string
    {
        return $this->server?->egg?->name ?? 'Unknown';
    }

    public function getLocationEmoji(): string
    {
        $searchable = strtolower(
            ($this->server?->node?->name ?? '') . ' ' .
            implode(' ', $this->server?->node?->tags ?? [])
        );

        // Multi-word place names must come before short codes
        $keywords = [
            'united states'  => '🇺🇸',
            'united kingdom' => '🇬🇧',
            'hong kong'      => '🇭🇰',
            'south africa'   => '🇿🇦',
            'south korea'    => '🇰🇷',
            'new zealand'    => '🇳🇿',
            'new york'       => '🇺🇸',
            'los angeles'    => '🇺🇸',
            'sao paulo'      => '🇧🇷',
            'são paulo'      => '🇧🇷',
            'north america'  => '🇺🇸',
            'southeast asia' => '🌏',
            // Cities
            'london'         => '🇬🇧',
            'manchester'     => '🇬🇧',
            'frankfurt'      => '🇩🇪',
            'berlin'         => '🇩🇪',
            'amsterdam'      => '🇳🇱',
            'paris'          => '🇫🇷',
            'singapore'      => '🇸🇬',
            'sydney'         => '🇦🇺',
            'melbourne'      => '🇦🇺',
            'toronto'        => '🇨🇦',
            'vancouver'      => '🇨🇦',
            'montreal'       => '🇨🇦',
            'tokyo'          => '🇯🇵',
            'osaka'          => '🇯🇵',
            'stockholm'      => '🇸🇪',
            'helsinki'       => '🇫🇮',
            'warsaw'         => '🇵🇱',
            'madrid'         => '🇪🇸',
            'barcelona'      => '🇪🇸',
            'rome'           => '🇮🇹',
            'milan'          => '🇮🇹',
            'zurich'         => '🇨🇭',
            'moscow'         => '🇷🇺',
            'mumbai'         => '🇮🇳',
            'bangalore'      => '🇮🇳',
            'seoul'          => '🇰🇷',
            'dubai'          => '🇦🇪',
            'johannesburg'   => '🇿🇦',
            'chicago'        => '🇺🇸',
            'dallas'         => '🇺🇸',
            'seattle'        => '🇺🇸',
            'atlanta'        => '🇺🇸',
            'miami'          => '🇺🇸',
            'denver'         => '🇺🇸',
            // Country names
            'germany'        => '🇩🇪',
            'deutschland'    => '🇩🇪',
            'france'         => '🇫🇷',
            'netherlands'    => '🇳🇱',
            'australia'      => '🇦🇺',
            'canada'         => '🇨🇦',
            'japan'          => '🇯🇵',
            'brazil'         => '🇧🇷',
            'sweden'         => '🇸🇪',
            'finland'        => '🇫🇮',
            'poland'         => '🇵🇱',
            'spain'          => '🇪🇸',
            'italy'          => '🇮🇹',
            'switzerland'    => '🇨🇭',
            'russia'         => '🇷🇺',
            'india'          => '🇮🇳',
            'korea'          => '🇰🇷',
            'uae'            => '🇦🇪',
            'europe'         => '🇪🇺',
        ];

        foreach ($keywords as $keyword => $emoji) {
            if (str_contains($searchable, $keyword)) {
                return $emoji;
            }
        }

        // Short ISO codes checked with word boundaries to avoid false matches
        $codes = [
            'us' => '🇺🇸', 'uk' => '🇬🇧', 'eu' => '🇪🇺',
            'de' => '🇩🇪', 'fr' => '🇫🇷', 'nl' => '🇳🇱',
            'sg' => '🇸🇬', 'au' => '🇦🇺', 'ca' => '🇨🇦',
            'jp' => '🇯🇵', 'br' => '🇧🇷', 'se' => '🇸🇪',
            'fi' => '🇫🇮', 'pl' => '🇵🇱', 'es' => '🇪🇸',
            'it' => '🇮🇹', 'ch' => '🇨🇭', 'ru' => '🇷🇺',
            'in' => '🇮🇳', 'hk' => '🇭🇰', 'kr' => '🇰🇷',
            'ae' => '🇦🇪', 'za' => '🇿🇦', 'nz' => '🇳🇿',
        ];

        foreach ($codes as $code => $emoji) {
            if (preg_match('/(?:^|[\s\-_])' . preg_quote($code, '/') . '(?:$|[\s\-_\d])/i', $searchable)) {
                return $emoji;
            }
        }

        return '🌐';
    }

    public function isMinecraft(): bool
    {
        $egg = $this->server?->egg;
        if (!$egg) {
            return false;
        }

        $name = strtolower($egg->name ?? '');
        $features = array_map('strtolower', $egg->inherit_features ?? $egg->features ?? []);
        $tags = array_map('strtolower', $egg->tags ?? []);

        return str_contains($name, 'minecraft')
            || in_array('minecraft_java', $features)
            || in_array('minecraft_bedrock', $features)
            || in_array('minecraft', $tags);
    }
}
