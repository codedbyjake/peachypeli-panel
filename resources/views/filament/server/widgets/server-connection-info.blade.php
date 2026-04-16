<x-filament::widget>
    <x-filament::section>
        <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            x-data="{
                copied: false,
                copy() {
                    navigator.clipboard.writeText('{{ $this->getAddress() }}');
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                }
            }"
        >
            {{-- Left: location and game metadata --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-1.5">
                    <span class="text-base leading-none">{{ $this->getLocationEmoji() }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $this->getNodeName() }}</span>
                </div>
                <span class="hidden sm:inline text-gray-300 dark:text-gray-600">&bull;</span>
                <div class="flex items-center gap-1.5">
                    <x-filament::icon
                        icon="tabler-device-gamepad-2"
                        class="h-4 w-4 shrink-0"
                    />
                    <span>{{ $this->getGameName() }}</span>
                </div>
            </div>

            {{-- Right: address + action buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Address --}}
                <span class="font-mono text-base font-semibold tracking-tight text-gray-900 dark:text-gray-100 select-all">
                    {{ $this->getAddress() }}
                </span>

                {{-- Copy button --}}
                <button
                    x-on:click="copy()"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium ring-1 ring-inset transition-colors
                           text-gray-600 dark:text-gray-400 ring-gray-300 dark:ring-gray-600
                           hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none"
                    :title="copied ? 'Copied!' : 'Copy address'"
                >
                    <x-filament::icon
                        x-show="!copied"
                        icon="tabler-copy"
                        class="h-3.5 w-3.5 shrink-0"
                    />
                    <x-filament::icon
                        x-show="copied"
                        icon="tabler-check"
                        class="h-3.5 w-3.5 shrink-0 text-success-500"
                    />
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </button>

                {{-- Steam connect button (not shown for Minecraft) --}}
                @unless($this->isMinecraft())
                    <a
                        href="{{ $this->getSteamConnectUrl() }}"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium ring-1 ring-inset transition-colors
                               bg-primary-600 text-white ring-primary-600
                               hover:bg-primary-500 hover:ring-primary-500 focus:outline-none"
                    >
                        <x-filament::icon
                            icon="tabler-brand-steam"
                            class="h-3.5 w-3.5 shrink-0"
                        />
                        Connect via Steam
                    </a>
                @endunless
            </div>
        </div>
    </x-filament::section>
</x-filament::widget>
