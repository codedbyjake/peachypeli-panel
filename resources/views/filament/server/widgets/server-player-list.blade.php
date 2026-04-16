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

                    // Poll every 30 seconds
                    setInterval(() => {
                        Livewire.dispatch('request-player-list', { gameType: '{{ $this->gameType }}' });
                    }, 30000);
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
                                pending:     false,
                                cmd:         '',
                                lbl:         '',
                                moreOpen:    false,
                                destructOpen: false,

                                ask(command, label) {
                                    this.cmd  = command;
                                    this.lbl  = label;
                                    this.pending      = true;
                                    this.moreOpen     = false;
                                    this.destructOpen = false;
                                },
                                confirm() {
                                    Livewire.dispatch('execute-rcon-command', {
                                        command: this.cmd,
                                        label:   this.lbl + ' {{ $pNameSafe }}',
                                    });
                                    this.pending = false;
                                },
                                cancel() { this.pending = false; }
                            }"
                            class="flex items-center justify-between gap-3 py-2 px-1 first:pt-0 last:pb-0"
                        >
                            {{-- Player info --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <x-filament::icon
                                    icon="tabler-user"
                                    class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
                                />
                                <span class="text-sm font-medium truncate text-gray-800 dark:text-gray-200">
                                    {{ $pName }}
                                </span>
                                @if ($pConnected)
                                    <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0 tabular-nums">
                                        {{ $pConnected }}
                                    </span>
                                @endif
                            </div>

                            {{-- Actions --}}
                            @if ($pSteamId)
                                <div class="flex items-center gap-1 shrink-0">

                                    {{-- Normal action buttons --}}
                                    <template x-if="!pending">
                                        <div class="flex items-center gap-1">

                                            {{-- Kick --}}
                                            <x-filament::icon-button
                                                icon="tabler-player-eject"
                                                color="warning"
                                                size="sm"
                                                tooltip="Kick"
                                                x-on:click="ask('kick {{ $pSteamId }}', 'Kick')"
                                            />

                                            {{-- Ban --}}
                                            <x-filament::icon-button
                                                icon="tabler-ban"
                                                color="danger"
                                                size="sm"
                                                tooltip="Ban"
                                                x-on:click="ask('ban {{ $pSteamId }}', 'Ban')"
                                            />

                                            {{-- Moderation dropdown: Mute / Unmute / Unban --}}
                                            <div class="relative" x-on:click.outside="moreOpen = false">
                                                <x-filament::icon-button
                                                    icon="tabler-dots"
                                                    color="gray"
                                                    size="sm"
                                                    tooltip="More actions"
                                                    x-on:click="moreOpen = !moreOpen; destructOpen = false"
                                                />
                                                <div
                                                    x-show="moreOpen"
                                                    x-transition
                                                    class="absolute right-0 z-20 mt-1 w-32 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1"
                                                >
                                                    <button
                                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        x-on:click="moreOpen = false; ask('mute {{ $pSteamId }}', 'Mute')"
                                                    >Mute</button>
                                                    <button
                                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        x-on:click="moreOpen = false; ask('unmute {{ $pSteamId }}', 'Unmute')"
                                                    >Unmute</button>
                                                    <button
                                                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        x-on:click="moreOpen = false; ask('unban {{ $pSteamId }}', 'Unban')"
                                                    >Unban</button>
                                                </div>
                                            </div>

                                            {{-- Destructive dropdown: Kill / Injure --}}
                                            <div class="relative" x-on:click.outside="destructOpen = false">
                                                <x-filament::icon-button
                                                    icon="tabler-bolt"
                                                    color="danger"
                                                    size="sm"
                                                    tooltip="Destructive actions"
                                                    x-on:click="destructOpen = !destructOpen; moreOpen = false"
                                                />
                                                <div
                                                    x-show="destructOpen"
                                                    x-transition
                                                    class="absolute right-0 z-20 mt-1 w-32 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 py-1"
                                                >
                                                    <button
                                                        class="w-full text-left px-3 py-1.5 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                        x-on:click="destructOpen = false; ask('global.killplayer {{ $pSteamId }}', 'Kill')"
                                                    >Kill</button>
                                                    <button
                                                        class="w-full text-left px-3 py-1.5 text-xs text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20"
                                                        x-on:click="destructOpen = false; ask('global.injureplayer {{ $pSteamId }}', 'Injure')"
                                                    >Injure</button>
                                                </div>
                                            </div>

                                        </div>
                                    </template>

                                    {{-- Inline confirmation --}}
                                    <template x-if="pending">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                                <span x-text="lbl + '?'"></span>
                                            </span>
                                            <x-filament::icon-button
                                                icon="tabler-check"
                                                color="success"
                                                size="sm"
                                                tooltip="Confirm"
                                                x-on:click="confirm()"
                                            />
                                            <x-filament::icon-button
                                                icon="tabler-x"
                                                color="gray"
                                                size="sm"
                                                tooltip="Cancel"
                                                x-on:click="cancel()"
                                            />
                                        </div>
                                    </template>

                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Minecraft and other games: simple name grid --}}
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
