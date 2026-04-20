<x-filament::widget>
    @assets
    @php
        $userFont = (string) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFont);
        $userFontSize = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFontSize);
        $userRows = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleRows);
    @endphp
    @if($userFont !== "monospace")
        <link rel="preload" href="{{ asset("storage/fonts/{$userFont}.ttf") }}" as="font" crossorigin>
        <style>
            @font-face {
                font-family: '{{ $userFont }}';
                src: url('{{ asset("storage/fonts/{$userFont}.ttf") }}');
            }
        </style>
    @endif
    @vite(['resources/js/console.js', 'resources/css/console.css'])
    @endassets

    @php
        $panelType = $this->getGamePanelType();
        $panelData = $this->getGamePanelData();
        $hasPanel  = $panelType !== 'none';
    @endphp

    @if ($hasPanel)
    <div
        x-data="{ panelVisible: true }"
        @collapse-game-panel.window="panelVisible = false"
        style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:stretch"
        :style="panelVisible ? 'display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:stretch' : ''"
    >
    @endif

        {{-- ── Console (left column) ── --}}
        <div style="display:flex;flex-direction:column;height:100%;border:1px solid #e5e7eb;border-radius:0.75rem;">
            <div id="terminal" wire:ignore style="flex:1;min-height:0;overflow:hidden;"></div>

            @if ($this->authorizeSendCommand())
                <div class="flex items-center gap-3 w-full py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-white/10" style="padding-left:10px;padding-right:10px;">
                    <x-filament::icon
                        icon="tabler-chevrons-right"
                        class="h-4 w-4 shrink-0 text-primary-500 dark:text-primary-400"
                    />
                    <input
                        id="send-command"
                        class="w-full focus:outline-none focus:ring-0 border-none bg-transparent text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 py-1"
                        type="text"
                        :readonly="{{ $this->canSendCommand() ? 'false' : 'true' }}"
                        title="{{ $this->canSendCommand() ? '' : trans('server/console.command_blocked_title') }}"
                        placeholder="{{ $this->canSendCommand() ? trans('server/console.command') : trans('server/console.command_blocked') }}"
                        wire:model="input"
                        wire:keydown.enter="enter"
                        wire:keydown.up.prevent="up"
                        wire:keydown.down="down"
                    >
                </div>
            @endif
        </div>

        {{-- ── Game panel (right column) ── --}}
        @if ($hasPanel)
        <div x-show="panelVisible" style="height:100%;">

            @if ($panelType === 'rust')
            {{-- ── Rust: interactive world map (pure PHP/CSS, no Alpine) ── --}}
            @php
                $rustMonuments = $panelData['monuments'] ?? [];
                $rustStats     = $panelData['stats'] ?? [];
                $rustMapSize   = (int) ($panelData['size'] ?? 3500);
                $tierColors    = ['danger' => '#ef4444', 'medium' => '#f59e0b', 'safe' => '#22c55e', 'minor' => '#9ca3af'];
            @endphp

            <style>
                .rm-pin:hover .rm-tip { display: block !important; }
            </style>

            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden" style="height:100%;display:flex;flex-direction:column;">

                {{-- Header --}}
                <div class="px-6 py-4 bg-white dark:bg-gray-900 border-b border-gray-950/5 dark:border-white/10 flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">World Map</span>
                    @if ($panelData['seed'] ?? '')
                        <span class="text-xs text-gray-400 dark:text-gray-500 tabular-nums shrink-0">
                            Seed: {{ $panelData['seed'] }} - Size: {{ $panelData['size'] }}
                        </span>
                    @endif
                </div>

                @if ($panelData['imageUrl'] ?? null)

                    {{-- Map + pin overlay --}}
                    <div style="position:relative; line-height:0; display:block;">
                        <a href="{{ $panelData['pageUrl'] }}" target="_blank" rel="noopener noreferrer">
                            <img
                                src="{{ $panelData['imageUrl'] }}"
                                alt="World Map"
                                style="width:100%; display:block;"
                                loading="lazy"
                            >
                        </a>

                        {{-- Pin overlay --}}
                        <div style="position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none;">
                            @foreach ($rustMonuments as $mon)
                                @php
                                    $pinLeft  = number_format(($mon['x'] + $rustMapSize / 2) / $rustMapSize * 100, 3);
                                    $pinTop   = number_format(($rustMapSize / 2 - $mon['y']) / $rustMapSize * 100, 3);
                                    $pinColor = $tierColors[$mon['tier']] ?? '#9ca3af';
                                @endphp
                                <div
                                    class="rm-pin"
                                    style="position:absolute; left:{{ $pinLeft }}%; top:{{ $pinTop }}%; transform:translate(-50%,-50%); pointer-events:auto; cursor:default; z-index:10;"
                                >
                                    <div style="width:9px; height:9px; border-radius:50%; background:{{ $pinColor }}; border:1.5px solid rgba(255,255,255,0.85); box-shadow:0 1px 3px rgba(0,0,0,0.5);"></div>
                                    <div
                                        class="rm-tip"
                                        style="display:none; position:absolute; bottom:100%; left:50%; transform:translateX(-50%); margin-bottom:5px; padding:3px 8px; border-radius:4px; background:rgba(15,23,42,0.93); color:#f1f5f9; font-size:11px; font-weight:500; white-space:nowrap; pointer-events:none; z-index:50; box-shadow:0 2px 8px rgba(0,0,0,0.4);"
                                    >{{ $mon['name'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Legend & stats --}}
                    <div class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-gray-950/5 dark:border-white/10 space-y-2" style="margin-top:auto;">

                        {{-- Tier legend --}}
                        @if (!empty($rustMonuments))
                            <div style="display:flex; flex-wrap:wrap; gap:8px 16px;">
                                @foreach (['danger' => ['#ef4444','High-tier'], 'medium' => ['#f59e0b','Mid-tier'], 'safe' => ['#22c55e','Safe zone'], 'minor' => ['#9ca3af','Minor']] as $tier => [$color, $label])
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:{{ $color }}; border:1.5px solid rgba(255,255,255,0.5); flex-shrink:0;"></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Map stats --}}
                        @if (!empty($rustStats))
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:2px 24px;">
                                @foreach ([['totalMonuments','Monuments'],['landPercentage','Land'],['mountains','Mountains']] as [$key, $label])
                                    @if ($rustStats[$key] ?? 0)
                                        <div style="display:flex; justify-content:space-between; gap:8px;">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-200" style="font-variant-numeric:tabular-nums;">
                                                {{ $rustStats[$key] }}{{ $key === 'landPercentage' ? '%' : '' }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        {{-- Biomes --}}
                        @if (!empty($rustStats['biomes']))
                            @php
                                $biomeNames = ['s' => 'Snow', 'd' => 'Desert', 'f' => 'Forest', 't' => 'Temperate', 'j' => 'Arid'];
                            @endphp
                            <div class="border-t border-gray-950/5 dark:border-white/10" style="padding-top:6px; display:flex; flex-wrap:wrap; gap:4px 12px;">
                                @foreach ($rustStats['biomes'] as $biome => $pct)
                                    @php $biomeName = $biomeNames[strtolower($biome)] ?? ucfirst(strtolower($biome)); @endphp
                                    <div style="display:flex; align-items:center; gap:4px;">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $biomeName }}</span>
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-200" style="font-variant-numeric:tabular-nums;">{{ round($pct, 1) }}%</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                    </div>

                @elseif ($panelData['seed'] ?? '')
                    <div class="p-5 flex flex-col items-center gap-3 text-center">
                        <x-filament::icon icon="tabler-map" class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Seed: {{ $panelData['seed'] }}</p>
                            <p class="text-xs text-gray-500">Size: {{ $panelData['size'] }}</p>
                        </div>
                        <a href="{{ $panelData['pageUrl'] }}" target="_blank" rel="noopener noreferrer"
                           class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                            View on Rustmaps.com &rarr;
                        </a>
                        @if (!config('services.rustmaps.key'))
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Set <code class="font-mono">RUSTMAPS_API_KEY</code> to show a map preview.
                            </p>
                        @endif
                    </div>
                @else
                    <div class="p-5 text-sm text-gray-400 dark:text-gray-500 text-center">
                        No world seed configured.
                    </div>
                @endif
            </div>

            @elseif ($panelType === 'ark')
            {{-- ── ARK: static map image ── --}}
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden" style="height:100%;display:flex;flex-direction:column;">
                <div class="px-6 py-4 bg-white dark:bg-gray-900 border-b border-gray-950/5 dark:border-white/10">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Map</span>
                </div>

                @if ($panelData['image'] ?? null)
                    <div class="relative">
                        <img
                            src="{{ $panelData['image'] }}"
                            alt="{{ $panelData['name'] ?? 'ARK Map' }}"
                            class="w-full block"
                            loading="lazy"
                            onerror="this.parentElement.style.display='none'"
                        >
                        @if ($panelData['name'] ?? '')
                            <div class="absolute bottom-0 left-0 right-0 px-3 py-2"
                                 style="background:linear-gradient(to top, rgba(0,0,0,.72) 0%, transparent 100%)">
                                <p class="text-sm font-semibold text-white">{{ $panelData['name'] }}</p>
                            </div>
                        @endif
                    </div>
                @elseif ($panelData['name'] ?? '')
                    <div class="p-5 text-center">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $panelData['name'] }}</p>
                    </div>
                @else
                    <div class="p-5 text-sm text-gray-400 dark:text-gray-500 text-center">
                        Map not detected.
                    </div>
                @endif
            </div>

            @elseif ($panelType === 'minecraft')
            {{-- ── Minecraft: server icon from public status API ── --}}
            <div
                x-data="{
                    icon:   null,
                    motd:   null,
                    loaded: false,
                    failed: false
                }"
                x-init="
                    fetch('https://api.mcsrvstat.us/3/{{ e($panelData['address'] ?? '') }}')
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.icon) {
                                icon   = data.icon;
                                motd   = (data.motd && data.motd.clean && data.motd.clean[0]) ? data.motd.clean[0] : null;
                                loaded = true;
                            } else {
                                failed = true;
                                $dispatch('collapse-game-panel');
                            }
                        })
                        .catch(() => {
                            failed = true;
                            $dispatch('collapse-game-panel');
                        })
                "
            >
                {{-- Loading state --}}
                <template x-if="!loaded && !failed">
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-5 flex items-center justify-center gap-2">
                        <x-filament::loading-indicator class="h-4 w-4 text-gray-400" />
                        <span class="text-sm text-gray-400">Loading server info&hellip;</span>
                    </div>
                </template>

                {{-- Icon loaded --}}
                <template x-if="loaded">
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                        <div class="px-6 py-4 bg-white dark:bg-gray-900 border-b border-gray-950/5 dark:border-white/10">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">Server Info</span>
                        </div>
                        <div class="p-5 flex flex-col items-center gap-3 text-center">
                            <img
                                :src="icon"
                                alt="Server Icon"
                                class="rounded-lg shadow-md"
                                style="width:64px;height:64px;image-rendering:pixelated"
                            >
                            <div class="space-y-1">
                                <p
                                    x-show="motd"
                                    x-text="motd"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-200 max-w-full truncate"
                                ></p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                    {{ $panelData['address'] ?? '' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            @endif

        </div>
        @endif

    @if ($hasPanel)
    </div>
    @endif

    @script
    <script>
        let theme = {
            background: 'rgba(19,26,32,0.7)',
            cursor: 'transparent',
            black: '#000000',
            red: '#E54B4B',
            green: '#9ECE58',
            yellow: '#FAED70',
            blue: '#396FE2',
            magenta: '#BB80B3',
            cyan: '#2DDAFD',
            white: '#d0d0d0',
            brightBlack: 'rgba(255, 255, 255, 0.2)',
            brightRed: '#FF5370',
            brightGreen: '#C3E88D',
            brightYellow: '#FFCB6B',
            brightBlue: '#82AAFF',
            brightMagenta: '#C792EA',
            brightCyan: '#89DDFF',
            brightWhite: '#ffffff',
            selection: '#FAF089'
        };

        let options = {
            fontSize: {{ $userFontSize }},
            fontFamily: '{{ $userFont }}, monospace',
            lineHeight: 1.2,
            disableStdin: true,
            cursorStyle: 'underline',
            cursorInactiveStyle: 'underline',
            allowTransparency: true,
            rows: {{ $userRows }},
            theme: theme
        };

        const { Terminal, FitAddon, WebLinksAddon, SearchAddon, SearchBarAddon, WebglAddon } = window.Xterm;

        const terminal = new Terminal(options);
        const fitAddon = new FitAddon();
        const webLinksAddon = new WebLinksAddon();
        const searchAddon = new SearchAddon();
        const searchAddonBar = new SearchBarAddon({ searchAddon });
        const webglAddon = new WebglAddon();
        terminal.loadAddon(fitAddon);
        terminal.loadAddon(webLinksAddon);
        terminal.loadAddon(searchAddon);
        terminal.loadAddon(searchAddonBar);
        terminal.loadAddon(webglAddon);

        terminal.open(document.getElementById('terminal'));

        fitAddon.fit();
        // Re-fit once layout has settled so xterm fills the flex container height.
        requestAnimationFrame(() => fitAddon.fit());

        window.addEventListener('load', () => {
            fitAddon.fit();
        });

        window.addEventListener('resize', () => {
            fitAddon.fit();
        });

        // Re-fit when the game panel collapses so the console fills the full width.
        window.addEventListener('collapse-game-panel', () => {
            setTimeout(() => fitAddon.fit(), 50);
        });

        terminal.attachCustomKeyEventHandler((event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
                navigator.clipboard.writeText(terminal.getSelection());
                return false;
            } else if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                searchAddonBar.show();
                return false;
            } else if (event.key === 'Escape') {
                searchAddonBar.hidden();
            }
            return true;
        });

        const TERMINAL_PRELUDE = '\u001b[1m\u001b[33mpelican@' + '{{ \Filament\Facades\Filament::getTenant()->name }}' + ' ~ \u001b[0m';

        const handleConsoleOutput = (line, prelude = false) =>
            terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handleTransferStatus = (status) =>
            status === 'failure' && terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');

        const handleDaemonErrorOutput = (line) =>
            terminal.writeln(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handlePowerChangeEvent = (state) =>
            terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

        const socket = new WebSocket("{{ $this->getSocket() }}");

        socket.onerror = (event) => {
            $wire.dispatchSelf('websocket-error');
        };

        // ── Player list intercept state ──────────────────────────────────────────
        let playerListMode = null;     // null | 'minecraft' | 'rust' | 'ark' | 'valheim' | 'palworld' | 'fivem'
        let playerListPlayers = [];
        let playerListCount = 0;
        let playerListMax = 0;
        let playerListFoundHeader = false;
        let playerListTimeout = null;
        let playerListCountless = false; // games that don't emit a total count header
        let playerListRawLines = [];     // debug: every stripped line captured during an active request

        const stripAnsi = (str) => str
            .replace(/\x1b\[[\d;]*[A-Za-z]/g, '')  // ANSI escape sequences
            .replace(/§./g, '')                       // Minecraft § colour codes
            .replace(/\r?\n$/, '');

        const flushPlayerList = (available) => {
            clearTimeout(playerListTimeout);
            Livewire.dispatch('player-list-update', {
                players: playerListPlayers,
                count: playerListCount,
                max: playerListMax,
                available: available,
            });
            playerListMode = null;
            playerListFoundHeader = false;
            playerListPlayers = [];
            playerListCount = 0;
            playerListMax = 0;
        };

        const interceptPlayerListLine = (rawLine) => {
            const line = stripAnsi(rawLine).trim();
            if (!line) return;
            if (playerListMode) playerListRawLines.push(line);

            if (playerListMode === 'minecraft') {
                // "There are X of a max of Y players online: Name1, Name2"
                const headerMatch = line.match(/there are (\d+) of a max of (\d+) players online[:.]\s*(.*)/i);
                if (headerMatch) {
                    playerListCount = parseInt(headerMatch[1], 10);
                    playerListMax   = parseInt(headerMatch[2], 10);
                    const inlineNames = headerMatch[3].trim();

                    if (playerListCount === 0) {
                        flushPlayerList(true);
                    } else if (inlineNames) {
                        // Java edition — all names on the same line
                        playerListPlayers = inlineNames.split(/,\s*/).map(n => n.trim()).filter(Boolean);
                        flushPlayerList(true);
                    } else {
                        // Bedrock — names follow on subsequent lines
                        playerListFoundHeader = true;
                    }
                } else if (playerListFoundHeader && line) {
                    playerListPlayers.push(...line.split(/,\s*/).map(n => n.trim()).filter(Boolean));
                    if (playerListPlayers.length >= playerListCount) {
                        flushPlayerList(true);
                    }
                }

            } else if (playerListMode === 'ark') {
                console.log('[ARK playerlist]', JSON.stringify(line));

                // "No Players Connected" and common variants → 0 players, still available
                if (/^no (?:players? connected|connected players?)/i.test(line) ||
                    /^0 players? online/i.test(line)) {
                    playerListFoundHeader = true;
                    flushPlayerList(true);
                    return;
                }

                // "0. PlayerName, 76561198XXXXXXXXX"
                const arkMatch = line.match(/^\d+\.\s+(.+),\s+(\d+)$/);
                if (arkMatch) {
                    playerListFoundHeader = true;
                    playerListPlayers.push({ name: arkMatch[1].trim(), steamId: arkMatch[2].trim() });
                }

            } else if (playerListMode === 'valheim') {
                // Header variants: "Players:" / "Player list:" / "Connected players:"
                if (/^(?:player\s*(?:list)?|connected\s*players?)\s*:?\s*$/i.test(line)) {
                    playerListFoundHeader = true;
                    return;
                }
                // "PlayerName - userid" or "PlayerName userid" or bare "PlayerName"
                const vhMatch = line.match(/^(.+?)(?:\s*[-–]\s*(\S+))?\s*$/);
                if (vhMatch && vhMatch[1].trim() && !/^(server|hostname|version|day|players?|world)/i.test(vhMatch[1])) {
                    playerListFoundHeader = true;
                    playerListPlayers.push({ name: vhMatch[1].trim(), userId: vhMatch[2] || '' });
                }

            } else if (playerListMode === 'palworld') {
                // CSV header: "name,playeruid,steamid"
                if (/^name,playeruid,steamid/i.test(line)) {
                    playerListFoundHeader = true;
                    return;
                }
                if (playerListFoundHeader) {
                    const parts = line.split(',');
                    if (parts.length >= 3 && parts[0].trim()) {
                        playerListPlayers.push({ name: parts[0].trim(), steamId: parts[2].trim() });
                    }
                }

            } else if (playerListMode === 'fivem') {
                // Status header: "ID  IP  Ping  Player"
                if (/^\s*ID\s+IP\s+Ping\s+Player/i.test(line)) {
                    playerListFoundHeader = true;
                    return;
                }
                if (playerListFoundHeader) {
                    // "  0   127.0.0.1:12345   15   PlayerName"
                    const fmMatch = line.match(/^\s*(\d+)\s+\S+\s+\d+\s+(.+?)\s*$/);
                    if (fmMatch) {
                        playerListPlayers.push({ name: fmMatch[2].trim(), playerId: fmMatch[1] });
                    }
                }

            } else if (playerListMode === 'rust') {
                // "players : X (Y max) (0 queued) ..."
                const countMatch = line.match(/^players\s*:\s*(\d+)\s*\((\d+)\s*max\)/i);
                if (countMatch) {
                    playerListCount = parseInt(countMatch[1], 10);
                    playerListMax   = parseInt(countMatch[2], 10);
                    playerListFoundHeader = true;
                    if (playerListCount === 0) {
                        flushPlayerList(true);
                    }
                } else if (playerListFoundHeader) {
                    // Skip the column-header row ("id  name  ping  connected  addr  owner")
                    if (/^\s*id\s+name/i.test(line)) return;

                    // Player rows: "  <id>  <"name">  <ping>  <H:MM:SS>  ..."
                    // Only the first four columns are guaranteed; addr/steamid are absent on
                    // vanilla Rust, so match those required fields then scan for a SteamID64
                    // separately so the row is never dropped just because SteamID is missing.
                    const playerRowMatch = line.match(
                        /^\s*\d+\s+(?:"([^"]*)"|(\S+))\s+\d+\s+([\d:]+)/
                    );
                    if (playerRowMatch) {
                        const steamIdMatch = line.match(/\b(76561\d{12})\b/);
                        playerListPlayers.push({
                            name:      playerRowMatch[1] || playerRowMatch[2],
                            connected: playerRowMatch[3],
                            steamId:   steamIdMatch ? steamIdMatch[1] : '',
                        });
                        if (playerListPlayers.length >= playerListCount) {
                            flushPlayerList(true);
                        }
                    }
                }
            }
        };

        Livewire.on('request-player-list', ({ gameType }) => {
            if (!gameType || gameType === 'unknown') return;

            // Reset state for a fresh capture
            clearTimeout(playerListTimeout);
            playerListMode         = gameType;
            playerListPlayers      = [];
            playerListCount        = 0;
            playerListMax          = 0;
            playerListFoundHeader  = false;
            playerListCountless    = ['ark', 'valheim', 'palworld', 'fivem'].includes(gameType);
            playerListRawLines     = [];

            const commands = {
                minecraft: 'list',
                rust:      'status',
                ark:       'listplayers',
                valheim:   'players',
                palworld:  'ShowPlayers',
                fivem:     'status',
            };
            const command = commands[gameType];
            if (!command) return;

            socket.send(JSON.stringify({
                'event': 'send command',
                'args': [command],
            }));

            // For countless games flush with available=true if a header or any players were seen.
            playerListTimeout = setTimeout(() => {
                // If ARK returned output but nothing parsed, show a debug notification so the
                // raw lines are visible and the regex can be adjusted accordingly.
                if (playerListMode === 'ark' && playerListRawLines.length > 0 && playerListPlayers.length === 0 && !playerListFoundHeader) {
                    const preview = playerListRawLines.slice(0, 10).join('\n');
                    console.warn('[ARK playerlist] No players parsed from output:\n' + preview);
                    Livewire.dispatch('rcon-command-response', {
                        label: 'ARK Player List — Debug (no players parsed)',
                        response: preview,
                    });
                }
                flushPlayerList(playerListCountless
                    ? (playerListFoundHeader || playerListPlayers.length > 0)
                    : false
                );
            }, 5000);
        });
        // ─────────────────────────────────────────────────────────────────────

        // ── RCON moderation command execution ────────────────────────────────
        let rconResponseLines = [];
        let rconResponseLabel = '';
        let rconResponseTimer = null;

        const flushRconResponse = () => {
            clearTimeout(rconResponseTimer);
            rconResponseTimer = null;
            Livewire.dispatch('rcon-command-response', {
                label: rconResponseLabel,
                response: rconResponseLines.join('\n').trim(),
            });
            rconResponseLines = [];
            rconResponseLabel = '';
        };

        Livewire.on('execute-rcon-command', ({ command, label }) => {
            if (playerListMode) return; // don't overlap with a player-list capture
            rconResponseLines = [];
            rconResponseLabel = label || 'Command sent';
            clearTimeout(rconResponseTimer);
            socket.send(JSON.stringify({ 'event': 'send command', 'args': [command] }));
            rconResponseTimer = setTimeout(flushRconResponse, 1500);
        });
        // ─────────────────────────────────────────────────────────────────────

        socket.onmessage = function(websocketMessageEvent) {
            let { event, args } = JSON.parse(websocketMessageEvent.data);

            switch (event) {
                case 'console output':
                case 'install output':
                    handleConsoleOutput(args[0]);
                    if (playerListMode) interceptPlayerListLine(args[0]);
                    if (rconResponseTimer !== null) {
                        const stripped = stripAnsi(args[0]).trim();
                        if (stripped) rconResponseLines.push(stripped);
                    }
                    break;
                case 'feature match':
                    Livewire.dispatch('mount-feature', { data: args[0] });
                    break;
                case 'status':
                    handlePowerChangeEvent(args[0]);

                    $wire.dispatch('console-status', { state: args[0] });
                    break;
                case 'transfer status':
                    handleTransferStatus(args[0]);
                    break;
                case 'daemon error':
                    handleDaemonErrorOutput(args[0]);
                    break;
                case 'stats':
                    $wire.dispatchSelf('store-stats', { data: args[0] });
                    break;
                case 'auth success':
                    socket.send(JSON.stringify({
                        'event': 'send logs',
                        'args': [null]
                    }));
                    break;
                case 'token expiring':
                case 'token expired':
                    $wire.dispatchSelf('token-request');
                    break;
            }
        };

        socket.onopen = (event) => {
            $wire.dispatchSelf('token-request');
        };

        Livewire.on('setServerState', ({ state, uuid }) => {
            const serverUuid = "{{ $this->server->uuid }}";
            if (uuid !== serverUuid) {
                return;
            }

            socket.send(JSON.stringify({
                'event': 'set state',
                'args': [state]
            }));
        });

        $wire.on('sendAuthRequest', ({ token }) => {
            socket.send(JSON.stringify({
                'event': 'auth',
                'args': [token]
            }));
        });

        $wire.on('sendServerCommand', ({ command }) => {
            socket.send(JSON.stringify({
                'event': 'send command',
                'args': [command]
            }));
        });
    </script>
    @endscript
</x-filament::widget>
