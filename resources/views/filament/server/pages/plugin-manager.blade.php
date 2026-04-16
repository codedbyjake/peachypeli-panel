<x-filament-panels::page>

    {{-- ── Unsupported game ──────────────────────────────────────────────── --}}
    @if (!$this->supported && !$this->needsCurseForgeKey)
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <x-filament::icon icon="tabler-puzzle-off" class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Plugin Manager not available</p>
                <p class="max-w-md text-xs text-gray-500 dark:text-gray-400">
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

    {{-- ── CurseForge API key missing ────────────────────────────────────── --}}
    @if ($this->needsCurseForgeKey)
        <x-filament::section>
            <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <x-filament::icon icon="tabler-key" class="h-10 w-10 text-warning-400" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">CurseForge API Key Required</p>
                <p class="max-w-md text-xs text-gray-500 dark:text-gray-400">
                    To browse and install ARK mods via CurseForge, set the
                    <code class="rounded bg-gray-100 px-1 py-0.5 font-mono dark:bg-gray-800">CURSEFORGE_API_KEY</code>
                    environment variable in your panel configuration.
                </p>
            </div>
        </x-filament::section>
    @endif

    {{-- ── Main UI (supported source detected) ──────────────────────────── --}}
    @if ($this->supported)

        {{-- Tab bar --}}
        <div class="flex items-center border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="setTab('browse')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                    {{ $this->activeTab === 'browse'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >Browse</button>

            <button
                wire:click="setTab('installed')"
                class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                    {{ $this->activeTab === 'installed'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                Installed
                @if ($this->installedPlugins->isNotEmpty())
                    <span class="inline-flex items-center justify-center rounded-full bg-primary-100 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                        {{ $this->installedPlugins->count() }}
                    </span>
                @endif
            </button>
        </div>

        {{-- ── Browse tab ─────────────────────────────────────────────────── --}}
        @if ($this->activeTab === 'browse')

            <x-filament::section>

                {{-- Search row --}}
                <div class="flex items-center gap-2 pb-4 border-b border-gray-100 dark:border-gray-800 mb-1">
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                            <x-filament::icon icon="tabler-search" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.400ms="search"
                            wire:keydown.enter="searchPlugins"
                            placeholder="Search {{ $this->sourceName }} plugins…"
                            class="h-9 w-full rounded-lg border border-gray-300 bg-white py-0 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-primary-500"
                        >
                    </div>
                    <x-filament::button
                        wire:click="searchPlugins"
                        wire:loading.attr="disabled"
                        wire:target="searchPlugins"
                        color="gray"
                        icon="tabler-search"
                        size="sm"
                    >
                        Search
                    </x-filament::button>
                </div>

                {{-- Section label --}}
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    {{ trim($this->search) ? 'Search results' : 'Popular ' . $this->sourceName . ' plugins' }}
                </p>

                {{-- Plugin list --}}
                @if (empty($this->results))
                    <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <x-filament::icon icon="tabler-mood-empty" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No plugins found.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->results as $plugin)
                            @php
                                $pluginId        = $plugin['id'] ?? '';
                                $alreadyInstalled = $this->isInstalled($pluginId);
                                $isInstalling    = $this->installingId === $pluginId;

                                // Compact download count: 580224 → 580.2K, 1200000 → 1.2M
                                $dl    = $plugin['downloads'] ?? 0;
                                $dlFmt = $dl >= 1_000_000
                                    ? rtrim(rtrim(number_format($dl / 1_000_000, 1), '0'), '.') . 'M'
                                    : ($dl >= 1_000
                                        ? rtrim(rtrim(number_format($dl / 1_000, 1), '0'), '.') . 'K'
                                        : (string) $dl);
                            @endphp

                            <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">

                                {{-- Thumbnail --}}
                                <div class="mt-0.5 shrink-0">
                                    @if ($plugin['icon_url'] ?? null)
                                        <img
                                            src="{{ $plugin['icon_url'] }}"
                                            alt=""
                                            class="h-9 w-9 rounded-lg object-cover ring-1 ring-gray-950/5 dark:ring-white/10"
                                        >
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                                            <x-filament::icon icon="tabler-puzzle" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                        </div>
                                    @endif
                                </div>

                                {{-- Info --}}
                                <div class="min-w-0 flex-1">
                                    {{-- Top row: name + actions --}}
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold leading-tight text-gray-900 dark:text-white">
                                                {{ $plugin['name'] }}
                                            </p>
                                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                                by {{ $plugin['author'] ?? 'Unknown' }}
                                            </p>
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex shrink-0 items-center gap-1.5">
                                            @if ($alreadyInstalled)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-900/20 dark:text-success-400 dark:ring-success-500/30">
                                                    <x-filament::icon icon="tabler-circle-check" class="h-3.5 w-3.5" />
                                                    Installed
                                                </span>
                                            @else
                                                <x-filament::button
                                                    color="primary"
                                                    size="sm"
                                                    :icon="$isInstalling ? 'tabler-loader' : 'tabler-download'"
                                                    wire:click="install('{{ e($pluginId) }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="install"
                                                    :disabled="(bool) $this->installingId"
                                                >
                                                    {{ $isInstalling ? 'Installing…' : 'Install' }}
                                                </x-filament::button>
                                            @endif

                                            @if ($plugin['url'] ?? null)
                                                <x-filament::icon-button
                                                    tag="a"
                                                    :href="$plugin['url']"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    icon="tabler-external-link"
                                                    color="gray"
                                                    size="sm"
                                                    tooltip="View on {{ $this->sourceName }}"
                                                />
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Description --}}
                                    @if ($plugin['description'] ?? null)
                                        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                            {{ $plugin['description'] }}
                                        </p>
                                    @endif

                                    {{-- Meta: version · downloads --}}
                                    <div class="mt-1.5 flex items-center gap-1.5 text-xs tabular-nums text-gray-400 dark:text-gray-500">
                                        @if (($plugin['version'] ?? null) && $plugin['version'] !== 'Latest')
                                            <span>{{ $plugin['version'] }}</span>
                                            <span class="text-gray-200 dark:text-gray-700">&middot;</span>
                                        @endif
                                        <span>{{ $dlFmt }} downloads</span>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @endif

            </x-filament::section>

        @endif

        {{-- ── Installed tab ───────────────────────────────────────────────── --}}
        @if ($this->activeTab === 'installed')

            <x-filament::section heading="Installed Plugins">
                @if ($this->installedPlugins->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <x-filament::icon icon="tabler-package-off" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No plugins installed yet.</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Browse the catalogue to install one.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->installedPlugins as $installed)
                            <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">

                                {{-- Left: plugin info --}}
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                                        <x-filament::icon icon="tabler-puzzle" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $installed->name }}
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $installed->source }}
                                            @if ($installed->version)
                                                &middot; {{ $installed->version }}
                                            @endif
                                            @if ($installed->install_dir)
                                                &middot; {{ $installed->install_dir }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                {{-- Right: actions --}}
                                <div class="flex shrink-0 items-center gap-1.5">
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
