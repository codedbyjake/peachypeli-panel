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
            @else
                <ul class="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($this->players as $player)
                        <li class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200 truncate">
                            <x-filament::icon
                                icon="tabler-user"
                                class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
                            />
                            <span class="truncate">{{ $player }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>
