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

    <div id="terminal" wire:ignore></div>

    @if ($this->authorizeSendCommand())
        <div class="flex items-center w-full border-top overflow-hidden dark:bg-gray-900"
             style="border-bottom-right-radius: 10px; border-bottom-left-radius: 10px;">
            <x-filament::icon
                icon="tabler-chevrons-right"
            />
            <input
                id="send-command"
                class="w-full focus:outline-none focus:ring-0 border-none dark:bg-gray-900 p-1"
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

        fitAddon.fit(); // Fixes SPA issues.

        window.addEventListener('load', () => {
            fitAddon.fit();
        });

        window.addEventListener('resize', () => {
            fitAddon.fit();
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
        let playerListMode = null;     // null | 'minecraft' | 'rust'
        let playerListPlayers = [];
        let playerListCount = 0;
        let playerListMax = 0;
        let playerListFoundHeader = false;
        let playerListTimeout = null;

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

                    // Player rows: "  12345   PlayerName   55   00:30:00  ..."
                    const nameMatch = line.match(/^\s*\d+\s+(\S+)/);
                    if (nameMatch) {
                        playerListPlayers.push(nameMatch[1]);
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
            playerListMode = gameType;
            playerListPlayers = [];
            playerListCount = 0;
            playerListMax = 0;
            playerListFoundHeader = false;

            const command = gameType === 'minecraft' ? 'list' : 'status';

            socket.send(JSON.stringify({
                'event': 'send command',
                'args': [command],
            }));

            // Time-out after 5 s if no usable response arrives
            playerListTimeout = setTimeout(() => flushPlayerList(false), 5000);
        });
        // ─────────────────────────────────────────────────────────────────────

        socket.onmessage = function(websocketMessageEvent) {
            let { event, args } = JSON.parse(websocketMessageEvent.data);

            switch (event) {
                case 'console output':
                case 'install output':
                    handleConsoleOutput(args[0]);
                    if (playerListMode) interceptPlayerListLine(args[0]);
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
