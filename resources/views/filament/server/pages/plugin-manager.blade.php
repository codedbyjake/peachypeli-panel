<x-filament-panels::page>

    {{-- Unsupported game --}}
    @if (!$this->supported && !$this->needsCurseForgeKey)
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-3 py-8 text-center">
                <x-filament::icon icon="tabler-puzzle-off" class="h-10 w-10 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Plugin Manager not available</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @if ($this->gameType === 'unknown')
                        The game type for this server could not be detected. Plugin management is supported for
                        Rust (uMod), Minecraft (Modrinth), Valheim and Palworld (Thunderstore), and ARK (CurseForge).
                    @else
                        Plugin management is not supported for <strong>{{ $this->gameType }}</strong>.
                    @endif
                </p>
            </div>
        </x-filament::section>
    @endif

    {{-- CurseForge API key missing --}}
    @if ($this->needsCurseForgeKey)
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-3 py-8 text-center">
                <x-filament::icon icon="tabler-key" class="h-10 w-10 text-warning-400" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">CurseForge API Key Required</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    To browse and install ARK mods via CurseForge, set the <code class="font-mono">CURSEFORGE_API_KEY</code>
                    environment variable in your panel configuration.
                </p>
            </div>
        </x-filament::section>
    @endif

    {{-- Supported game --}}
    @if ($this->supported)

        {{-- Tab bar --}}
        <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-700 mb-4">
            <button
                wire:click="setTab('browse')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $this->activeTab === 'browse'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                Browse
            </button>
            <button
                wire:click="setTab('installed')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $this->activeTab === 'installed'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                Installed
                @if ($this->installedPlugins->isNotEmpty())
                    <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ $this->installedPlugins->count() }}
                    </span>
                @endif
            </button>
        </div>

        {{-- Browse tab --}}
        @if ($this->activeTab === 'browse')

            {{-- Plugin detail view --}}
            @if ($this->selectedPlugin)
                @php
                    $plugin        = $this->selectedPlugin;
                    $alreadyInstalled = $this->isInstalled($plugin['id'] ?? '');
                @endphp
                <x-filament::section>
                    <div class="flex flex-col gap-6">

                        {{-- Back button + header --}}
                        <div class="flex items-start gap-4">
                            <x-filament::icon-button
                                icon="tabler-arrow-left"
                                color="gray"
                                size="sm"
                                wire:click="clearSelectedPlugin"
                                tooltip="Back to results"
                            />
                            <div class="flex flex-1 items-center gap-4 min-w-0">
                                @if ($plugin['icon_url'] ?? null)
                                    <img src="{{ $plugin['icon_url'] }}" alt="{{ $plugin['name'] }}" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                                @else
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                                        <x-filament::icon icon="tabler-puzzle" class="h-6 w-6 text-gray-400" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-base font-bold text-gray-900 dark:text-white">{{ $plugin['name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">by {{ $plugin['author'] ?? 'Unknown' }} &bull; {{ $plugin['version'] ?? 'unknown' }} &bull; {{ number_format($plugin['downloads'] ?? 0) }} downloads</p>
                                </div>
                            </div>
                        </div>

                        {{-- Description --}}
                        @if ($plugin['description'] ?? null)
                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $plugin['description'] }}</p>
                        @endif

                        {{-- Actions --}}
                        <div class="flex items-center gap-3">
                            @if ($alreadyInstalled)
                                <x-filament::button color="gray" disabled icon="tabler-circle-check">
                                    Already Installed
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    color="primary"
                                    icon="tabler-download"
                                    wire:loading.attr="disabled"
                                    wire:click="install(
                                        @js($plugin['id'] ?? ''),
                                        @js($plugin['download_url']),
                                        @js($this->getInstallDir()),
                                        @js($plugin['file_name'] ?? ($plugin['name'] . '.jar')),
                                        @js($plugin['name']),
                                        @js($plugin['version'] ?? 'unknown')
                                    )"
                                >
                                    Install
                                </x-filament::button>
                            @endif

                            @if ($plugin['url'] ?? null)
                                <x-filament::button
                                    tag="a"
                                    :href="$plugin['url']"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    color="gray"
                                    icon="tabler-external-link"
                                    outlined
                                >
                                    View on {{ $this->sourceName }}
                                </x-filament::button>
                            @endif
                        </div>

                    </div>
                </x-filament::section>

            @else
                {{-- Search bar --}}
                <x-filament::section>
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                <x-filament::icon icon="tabler-search" class="h-4 w-4 text-gray-400" />
                            </div>
                            <input
                                type="text"
                                wire:model.live.debounce.500ms="search"
                                wire:keydown.enter="searchPlugins"
                                placeholder="Search {{ $this->sourceName }} plugins..."
                                class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-primary-500"
                            >
                        </div>
                        <x-filament::button wire:click="searchPlugins" color="primary" icon="tabler-search">
                            Search
                        </x-filament::button>
                    </div>
                </x-filament::section>

                {{-- Results grid --}}
                <x-filament::section :heading="trim($this->search) ? 'Search Results' : 'Popular ' . $this->sourceName . ' Plugins'">
                    @if (empty($this->results))
                        <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                            <x-filament::icon icon="tabler-mood-empty" class="h-8 w-8 text-gray-400" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">No plugins found.</p>
                        </div>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($this->results as $plugin)
                                @php $alreadyInstalled = $this->isInstalled($plugin['id'] ?? ''); @endphp
                                <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">

                                    {{-- Plugin header --}}
                                    <div class="flex items-start gap-3">
                                        @if ($plugin['icon_url'] ?? null)
                                            <img src="{{ $plugin['icon_url'] }}" alt="{{ $plugin['name'] }}" class="h-10 w-10 shrink-0 rounded-lg object-cover">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                                                <x-filament::icon icon="tabler-puzzle" class="h-5 w-5 text-gray-400" />
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $plugin['name'] }}</p>
                                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">by {{ $plugin['author'] ?? 'Unknown' }}</p>
                                        </div>
                                    </div>

                                    {{-- Description --}}
                                    @if ($plugin['description'] ?? null)
                                        <p class="line-clamp-2 text-xs text-gray-600 dark:text-gray-400">{{ $plugin['description'] }}</p>
                                    @endif

                                    {{-- Meta --}}
                                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                        <span>v{{ $plugin['version'] ?? '?' }}</span>
                                        <span>&bull;</span>
                                        <span>{{ number_format($plugin['downloads'] ?? 0) }} downloads</span>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex items-center gap-2 pt-1">
                                        @if ($alreadyInstalled)
                                            <x-filament::button color="gray" size="sm" disabled icon="tabler-circle-check">
                                                Installed
                                            </x-filament::button>
                                        @else
                                            <x-filament::button
                                                color="primary"
                                                size="sm"
                                                icon="tabler-download"
                                                wire:loading.attr="disabled"
                                                wire:target="install"
                                                wire:click="install(
                                                    @js($plugin['id'] ?? ''),
                                                    @js($plugin['download_url']),
                                                    @js($this->getInstallDir()),
                                                    @js($plugin['file_name'] ?? ($plugin['name'] . '.jar')),
                                                    @js($plugin['name']),
                                                    @js($plugin['version'] ?? 'unknown')
                                                )"
                                            >
                                                Install
                                            </x-filament::button>
                                        @endif

                                        <x-filament::button
                                            color="gray"
                                            size="sm"
                                            outlined
                                            wire:click="selectPlugin(@js($plugin['id'] ?? ''))"
                                        >
                                            Details
                                        </x-filament::button>
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @endif

        @endif

        {{-- Installed tab --}}
        @if ($this->activeTab === 'installed')
            <x-filament::section heading="Installed Plugins">
                @if ($this->installedPlugins->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                        <x-filament::icon icon="tabler-package-off" class="h-8 w-8 text-gray-400" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No plugins installed yet. Browse the catalogue to install one.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->installedPlugins as $installed)
                            <div class="flex items-center justify-between gap-4 py-3 px-1 first:pt-1 last:pb-1">

                                {{-- Plugin info --}}
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                        {{ $installed->name }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $installed->source }} &bull; v{{ $installed->version }}
                                        @if ($installed->install_dir)
                                            &bull; {{ $installed->install_dir }}
                                        @endif
                                    </p>
                                </div>

                                {{-- Actions --}}
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-filament::button
                                        icon="tabler-refresh"
                                        color="gray"
                                        size="sm"
                                        outlined
                                        wire:click="update({{ $installed->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="update({{ $installed->id }})"
                                    >
                                        Update
                                    </x-filament::button>

                                    <x-filament::button
                                        icon="tabler-trash"
                                        color="danger"
                                        size="sm"
                                        outlined
                                        wire:click="uninstall({{ $installed->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="uninstall({{ $installed->id }})"
                                        wire:confirm="Remove '{{ e($installed->name) }}'? This will delete the plugin file from the server."
                                    >
                                        Remove
                                    </x-filament::button>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endif

    @endif

</x-filament-panels::page>
