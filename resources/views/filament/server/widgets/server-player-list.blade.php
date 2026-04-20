<x-filament::widget>
    <x-filament::section
        :heading="'Players Online' . ($this->loaded && $this->available ? ' (' . $this->playerCount . '/' . ($this->maxPlayers ?: '∞') . ')' : '')"
    >
        <div
            x-data="{
                init() {
                    // Initial request after a short delay to ensure the console WebSocket listener is ready
                    setTimeout(() => {
                        Livewire.dispatch('request-player-list', { gameType: '{{ $this->gameType }}' });
                    }, 1500);

                    // Poll once per hour
                    setInterval(() => {
                        Livewire.dispatch('request-player-list', { gameType: '{{ $this->gameType }}' });
                    }, 3600000);
                }
            }"
        >
            @if ($this->gameType === 'unknown')
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Player list is not available for this game type.
                </p>
            @elseif (!$this->loaded)
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::loading-indicator class="h-4 w-4" />
                    <span>Fetching player list&hellip;</span>
                </div>
            @elseif (!$this->available)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Server offline or player list unavailable.
                </p>
            @elseif ($this->playerCount === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No players are currently online.
                </p>
            @elseif ($this->gameType === 'rust')
                {{-- Rust: table-style rows with per-player moderation actions --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->players as $player)
                        @php
                            $pName      = is_array($player) ? ($player['name']      ?? '?')  : $player;
                            $pSteamId   = is_array($player) ? ($player['steamId']   ?? '')   : '';
                            $pConnected = is_array($player) ? ($player['connected'] ?? '')   : '';
                            $pNameSafe  = e($pName);
                        @endphp
                        <div
                            x-data="{
                                pending:      false,
                                cmd:          '',
                                lbl:          '',
                                reason:       '',
                                needsReason:  false,
                                moreOpen:     false,
                                destructOpen: false,

                                ask(command, label, withReason = false) {
                                    this.cmd          = command;
                                    this.lbl          = label;
                                    this.reason       = '';
                                    this.needsReason  = withReason;
                                    this.pending      = true;
                                    this.moreOpen     = false;
                                    this.destructOpen = false;
                                },
                                confirm() {
                                    const fullCmd = (this.needsReason && this.reason.trim())
                                        ? this.cmd + ' ' + this.reason.trim()
                                        : this.cmd;
                                    Livewire.dispatch('execute-rcon-command', {
                                        command: fullCmd,
                                        label:   this.lbl + ' {{ $pNameSafe }}',
                                    });
                                    this.pending = false;
                                },
                                cancel() { this.pending = false; }
                            }"
                            class="flex justify-between gap-4 py-3 px-1 first:pt-1 last:pb-1"
                            :class="pending ? 'items-start' : 'items-center'"
                        >
                            {{-- Left: player info --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <x-filament::icon
                                    icon="tabler-user"
                                    class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                                />
                                <span class="text-sm font-semibold truncate text-gray-800 dark:text-gray-200">
                                    {{ $pName }}
                                </span>
                                @if ($pConnected)
                                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 tabular-nums">
                                        {{ $pConnected }}
                                    </span>
                                @endif
                            </div>

                            {{-- Right: actions --}}
                            @if ($pSteamId)
                                <div class="shrink-0">

                                    {{-- Normal action buttons --}}
                                    <template x-if="!pending">
                                        <div class="flex items-center gap-2">

                                            {{-- Kick --}}
                                            <x-filament::button
                                                icon="tabler-player-eject"
                                                color="warning"
                                                size="sm"
                                                x-on:click="ask('kick {{ $pSteamId }}', 'Kick', true)"
                                            >
                                                Kick
                                            </x-filament::button>

                                            {{-- Ban --}}
                                            <x-filament::button
                                                icon="tabler-ban"
                                                color="danger"
                                                size="sm"
                                                x-on:click="ask('ban {{ $pSteamId }}', 'Ban', true)"
                                            >
                                                Ban
                                            </x-filament::button>

                                            {{-- Moderation dropdown: Mute / Unmute / Unban --}}
                                            <div class="relative" x-on:click.outside="moreOpen = false">
                                                <x-filament::button
                                                    icon="tabler-dots"
                                                    color="gray"
                                                    size="sm"
                                                    x-on:click="moreOpen = !moreOpen; destructOpen = false"
                                                >
                                                    More
                                                </x-filament::button>
                                                <div
                                                    x-show="moreOpen"
                                                    x-transition
                                                    class="absolute right-0 z-20 mt-1 w-36 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1"
                                                >
                                                    <button
                                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        x-on:click="moreOpen = false; ask('mute {{ $pSteamId }}', 'Mute', true)"
                                                    >Mute</button>
                                                    <button
                                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        x-on:click="moreOpen = false; ask('unmute {{ $pSteamId }}', 'Unmute')"
                                                    >Unmute</button>
                                                    <button
                                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        x-on:click="moreOpen = false; ask('unban {{ $pSteamId }}', 'Unban')"
                                                    >Unban</button>
                                                </div>
                                            </div>

                                            {{-- Destructive dropdown: Kill / Injure --}}
                                            <div class="relative" x-on:click.outside="destructOpen = false">
                                                <x-filament::button
                                                    icon="tabler-bolt"
                                                    color="danger"
                                                    size="sm"
                                                    outlined
                                                    x-on:click="destructOpen = !destructOpen; moreOpen = false"
                                                >
                                                    Actions
                                                </x-filament::button>
                                                <div
                                                    x-show="destructOpen"
                                                    x-transition
                                                    class="absolute right-0 z-20 mt-1 w-36 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1"
                                                >
                                                    <button
                                                        class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                        x-on:click="destructOpen = false; ask('global.killplayer {{ $pSteamId }}', 'Kill')"
                                                    >Kill</button>
                                                    <button
                                                        class="w-full text-left px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20"
                                                        x-on:click="destructOpen = false; ask('global.injureplayer {{ $pSteamId }}', 'Injure')"
                                                    >Injure</button>
                                                </div>
                                            </div>

                                        </div>
                                    </template>

                                    {{-- Inline confirmation (with optional reason input) --}}
                                    <template x-if="pending">
                                        <div class="flex flex-col items-end gap-2">

                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                <span x-text="lbl + ' {{ $pNameSafe }}?'"></span>
                                            </p>

                                            <template x-if="needsReason">
                                                <input
                                                    type="text"
                                                    x-model="reason"
                                                    placeholder="Reason (optional)"
                                                    x-on:keydown.enter="confirm()"
                                                    x-on:keydown.escape="cancel()"
                                                    class="w-52 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                                                >
                                            </template>

                                            <div class="flex items-center gap-2">
                                                <x-filament::button
                                                    icon="tabler-check"
                                                    color="success"
                                                    size="sm"
                                                    x-on:click="confirm()"
                                                >
                                                    Confirm
                                                </x-filament::button>
                                                <x-filament::button
                                                    icon="tabler-x"
                                                    color="gray"
                                                    size="sm"
                                                    x-on:click="cancel()"
                                                >
                                                    Cancel
                                                </x-filament::button>
                                            </div>

                                        </div>
                                    </template>

                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif ($this->gameType === 'minecraft')
                {{-- Minecraft: table-style rows with per-player moderation actions --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->players as $player)
                        @php
                            $pName     = is_array($player) ? ($player['name'] ?? $player) : $player;
                            $pNameSafe = e($pName);
                        @endphp
                        <div
                            x-data="{
                                pending:     false,
                                cmd:         '',
                                lbl:         '',
                                reason:      '',
                                needsReason: false,
                                moreOpen:    false,
                                wlOpen:      false,

                                ask(command, label, withReason = false) {
                                    this.cmd         = command;
                                    this.lbl         = label;
                                    this.reason      = '';
                                    this.needsReason = withReason;
                                    this.pending     = true;
                                    this.moreOpen    = false;
                                    this.wlOpen      = false;
                                },
                                confirm() {
                                    const fullCmd = (this.needsReason && this.reason.trim())
                                        ? this.cmd + ' ' + this.reason.trim()
                                        : this.cmd;
                                    Livewire.dispatch('execute-rcon-command', {
                                        command: fullCmd,
                                        label:   this.lbl + ' {{ $pNameSafe }}',
                                    });
                                    this.pending = false;
                                },
                                cancel() { this.pending = false; }
                            }"
                            class="flex justify-between gap-4 py-3 px-1 first:pt-1 last:pb-1"
                            :class="pending ? 'items-start' : 'items-center'"
                        >
                            {{-- Left: player name --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <x-filament::icon
                                    icon="tabler-user"
                                    class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                                />
                                <span class="text-sm font-semibold truncate text-gray-800 dark:text-gray-200">
                                    {{ $pName }}
                                </span>
                            </div>

                            {{-- Right: actions --}}
                            <div class="shrink-0">

                                {{-- Normal action buttons --}}
                                <template x-if="!pending">
                                    <div class="flex items-center gap-2">

                                        {{-- Kick --}}
                                        <x-filament::button
                                            icon="tabler-player-eject"
                                            color="warning"
                                            size="sm"
                                            x-on:click="ask('kick {{ $pNameSafe }}', 'Kick', true)"
                                        >Kick</x-filament::button>

                                        {{-- Ban --}}
                                        <x-filament::button
                                            icon="tabler-ban"
                                            color="danger"
                                            size="sm"
                                            x-on:click="ask('ban {{ $pNameSafe }}', 'Ban', true)"
                                        >Ban</x-filament::button>

                                        {{-- Account actions dropdown: Pardon, Op, Deop --}}
                                        <div class="relative" x-on:click.outside="moreOpen = false">
                                            <x-filament::button
                                                icon="tabler-dots"
                                                color="gray"
                                                size="sm"
                                                x-on:click="moreOpen = !moreOpen; wlOpen = false"
                                            >More</x-filament::button>
                                            <div
                                                x-show="moreOpen"
                                                x-transition
                                                class="absolute right-0 z-20 mt-1 w-40 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1"
                                            >
                                                <button
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                    x-on:click="moreOpen = false; ask('pardon {{ $pNameSafe }}', 'Pardon')"
                                                >Pardon (Unban)</button>
                                                <button
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                    x-on:click="moreOpen = false; ask('op {{ $pNameSafe }}', 'Op')"
                                                >Op</button>
                                                <button
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                    x-on:click="moreOpen = false; ask('deop {{ $pNameSafe }}', 'Deop')"
                                                >Deop</button>
                                            </div>
                                        </div>

                                        {{-- Whitelist dropdown --}}
                                        <div class="relative" x-on:click.outside="wlOpen = false">
                                            <x-filament::button
                                                icon="tabler-list-check"
                                                color="gray"
                                                size="sm"
                                                outlined
                                                x-on:click="wlOpen = !wlOpen; moreOpen = false"
                                            >Whitelist</x-filament::button>
                                            <div
                                                x-show="wlOpen"
                                                x-transition
                                                class="absolute right-0 z-20 mt-1 w-40 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1"
                                            >
                                                <button
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                    x-on:click="wlOpen = false; ask('whitelist add {{ $pNameSafe }}', 'Whitelist Add')"
                                                >Add to Whitelist</button>
                                                <button
                                                    class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    x-on:click="wlOpen = false; ask('whitelist remove {{ $pNameSafe }}', 'Whitelist Remove')"
                                                >Remove from Whitelist</button>
                                            </div>
                                        </div>

                                    </div>
                                </template>

                                {{-- Inline confirmation (with optional reason input) --}}
                                <template x-if="pending">
                                    <div class="flex flex-col items-end gap-2">

                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                            <span x-text="lbl + ' {{ $pNameSafe }}?'"></span>
                                        </p>

                                        <template x-if="needsReason">
                                            <input
                                                type="text"
                                                x-model="reason"
                                                placeholder="Reason (optional)"
                                                x-on:keydown.enter="confirm()"
                                                x-on:keydown.escape="cancel()"
                                                class="w-52 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                                            >
                                        </template>

                                        <div class="flex items-center gap-2">
                                            <x-filament::button
                                                icon="tabler-check"
                                                color="success"
                                                size="sm"
                                                x-on:click="confirm()"
                                            >Confirm</x-filament::button>
                                            <x-filament::button
                                                icon="tabler-x"
                                                color="gray"
                                                size="sm"
                                                x-on:click="cancel()"
                                            >Cancel</x-filament::button>
                                        </div>

                                    </div>
                                </template>

                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Other games: simple name grid --}}
                <ul class="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($this->players as $player)
                        <li class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 truncate">
                            <x-filament::icon
                                icon="tabler-user"
                                class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
                            />
                            <span class="truncate">{{ is_array($player) ? ($player['name'] ?? $player) : $player }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>
