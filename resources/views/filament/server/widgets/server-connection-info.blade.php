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


        </div>
    </x-filament::section>
</x-filament::widget>
