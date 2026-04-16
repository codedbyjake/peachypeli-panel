<x-filament::widget>
    <x-filament::section heading="Connection">
        <div class="flex w-full items-center justify-between gap-4">

            {{-- Node --}}
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="tabler-server"
                    class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                />
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Node</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $this->getNodeName() }}</p>
                </div>
            </div>

            {{-- Game --}}
            <div class="flex items-center gap-3">
                <x-filament::icon
                    icon="tabler-device-gamepad-2"
                    class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                />
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Game</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $this->getGameName() }}</p>
                </div>
            </div>

            {{-- IP address + copy --}}
            <div class="flex items-center gap-3">
                <span class="select-all rounded-md bg-gray-100 px-3 py-1.5 font-mono text-sm font-semibold text-gray-900 dark:bg-gray-800 dark:text-gray-100">
                    {{ $this->getAddress() }}
                </span>
                <x-filament::icon-button
                    icon="tabler-copy"
                    color="gray"
                    size="sm"
                    tooltip="Copy address"
                    x-on:click="
                        navigator.clipboard.writeText('{{ e($this->getAddress()) }}');
                        $tooltip('Copied!', { theme: $store.theme, timeout: 2000 })
                    "
                />
            </div>

            {{-- Steam connect --}}
            @unless($this->isMinecraft())
                <x-filament::button
                    tag="a"
                    :href="$this->getSteamConnectUrl()"
                    icon="tabler-brand-steam"
                    color="primary"
                    size="sm"
                >
                    Connect via Steam
                </x-filament::button>
            @endunless

        </div>
    </x-filament::section>
</x-filament::widget>
