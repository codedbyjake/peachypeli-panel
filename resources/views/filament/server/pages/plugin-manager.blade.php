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

    {{-- ── Main UI ────────────────────────────────────────────────────────── --}}
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
                class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors
                    {{ $this->activeTab === 'installed'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                {{--
                    Count is interpolated directly into the label string rather than using a
                    child <span>, because CSS gap does not apply between a text node and an
                    element node — causing "Installed1" instead of "Installed (1)".
                --}}
                {{ 'Installed' . ($this->installedPlugins->isNotEmpty() ? ' (' . $this->installedPlugins->count() . ')' : '') }}
            </button>
        </div>

        {{-- ── Browse tab ─────────────────────────────────────────────────── --}}
        @if ($this->activeTab === 'browse')

            <x-filament::section>

                {{-- Search row --}}
                <div class="flex items-center gap-2 border-b border-gray-100 pb-4 dark:border-gray-800">
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
                    >Search</x-filament::button>
                </div>

                {{-- Results heading --}}
                <p class="mb-4 mt-4 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    {{ trim($this->search) ? 'Search results' : 'Popular ' . $this->sourceName . ' plugins' }}
                </p>

                {{-- Empty state --}}
                @if (empty($this->results))
                    <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <x-filament::icon icon="tabler-mood-empty" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">No plugins found.</p>
                    </div>

                @else
                    <style>
                        .pm-grid {
                            display: grid;
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                            gap: 1rem;
                        }
                        @media (min-width: 768px) {
                            .pm-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
                        }
                        @media (min-width: 1024px) {
                            .pm-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
                        }
                        .pm-install-btn:hover:not(:disabled) { filter: brightness(1.1); }
                        .pm-install-btn:disabled { opacity: 0.6; cursor: not-allowed; }
                        .pm-ext-link:hover { opacity: 0.7; }
                        .pm-desc {
                            display: -webkit-box;
                            -webkit-line-clamp: 2;
                            -webkit-box-orient: vertical;
                            overflow: hidden;
                        }
                    </style>

                    <div class="pm-grid">
                        @foreach ($this->results as $plugin)
                            @php
                                $pluginId         = $plugin['id'] ?? '';
                                $alreadyInstalled = $this->isInstalled($pluginId);
                                $isInstalling     = $this->installingId === $pluginId;

                                $dl    = $plugin['downloads'] ?? 0;
                                $dlFmt = $dl >= 1_000_000
                                    ? rtrim(rtrim(number_format($dl / 1_000_000, 1), '0'), '.') . 'M'
                                    : ($dl >= 1_000
                                        ? rtrim(rtrim(number_format($dl / 1_000, 1), '0'), '.') . 'K'
                                        : (string) $dl);
                            @endphp

                            {{-- Card --}}
                            <div style="display:flex;flex-direction:column;overflow:hidden;border-radius:0.75rem;border:1px solid #e5e7eb;background:#fff" class="dark:border-gray-700 dark:bg-gray-900">

                                {{-- Thumbnail — fixed height, image centred and size-constrained --}}
                                <div style="display:flex;height:6rem;flex-shrink:0;align-items:center;justify-content:center;padding:0 1rem" class="bg-gray-50 dark:bg-gray-800">
                                    @if ($plugin['icon_url'] ?? null)
                                        <img
                                            src="{{ $plugin['icon_url'] }}"
                                            alt=""
                                            style="max-height:3rem;width:auto;border-radius:0.5rem;object-fit:cover;box-shadow:0 1px 2px 0 rgba(0,0,0,.05)"
                                        >
                                    @else
                                        <div style="display:flex;height:3rem;width:3rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:0.5rem" class="bg-gray-200 dark:bg-gray-700">
                                            <x-filament::icon icon="tabler-puzzle" class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                                        </div>
                                    @endif
                                </div>

                                {{-- Card body --}}
                                <div style="display:flex;flex:1 1 0%;flex-direction:column;padding:0.75rem">

                                    <p style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.875rem;font-weight:600;line-height:1.375" class="text-gray-900 dark:text-white">
                                        {{ $plugin['name'] }}
                                    </p>

                                    <p style="margin-top:0.125rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.75rem" class="text-gray-500 dark:text-gray-400">
                                        by {{ $plugin['author'] ?? 'Unknown' }}
                                    </p>

                                    @if ($plugin['description'] ?? null)
                                        <p class="pm-desc text-gray-400 dark:text-gray-500" style="margin-top:0.375rem;font-size:0.75rem;line-height:1.625">
                                            {{ $plugin['description'] }}
                                        </p>
                                    @endif

                                    {{-- Footer pushed to bottom --}}
                                    <div style="margin-top:auto;padding-top:0.75rem">

                                        {{-- Meta row: version · downloads · external link --}}
                                        <div style="display:flex;align-items:center;gap:0.375rem;margin-bottom:0.5rem;font-size:0.75rem;font-variant-numeric:tabular-nums" class="text-gray-400 dark:text-gray-500">
                                            @if (($plugin['version'] ?? null) && $plugin['version'] !== 'Latest')
                                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $plugin['version'] }}</span>
                                                <span style="flex-shrink:0" class="text-gray-200 dark:text-gray-700">&middot;</span>
                                            @endif
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $dlFmt }}</span>
                                            @if ($plugin['url'] ?? null)
                                                <a
                                                    href="{{ $plugin['url'] }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="pm-ext-link text-gray-300 dark:text-gray-600"
                                                    style="margin-left:auto;flex-shrink:0;text-decoration:none;transition:opacity .15s"
                                                    title="View on {{ $this->sourceName }}"
                                                >
                                                    <x-filament::icon icon="tabler-external-link" class="h-3.5 w-3.5" />
                                                </a>
                                            @endif
                                        </div>

                                        @if ($alreadyInstalled)
                                            <div style="display:flex;width:100%;align-items:center;justify-content:center;gap:0.375rem;border-radius:0.5rem;padding:0.375rem 0;font-size:0.75rem;font-weight:500" class="border border-success-200 bg-success-50 text-success-700 dark:border-success-800/50 dark:bg-success-900/20 dark:text-success-400">
                                                <x-filament::icon icon="tabler-circle-check" class="h-3.5 w-3.5 shrink-0" />
                                                Installed
                                            </div>
                                        @else
                                            <button
                                                wire:click="install('{{ e($pluginId) }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="install"
                                                @disabled((bool) $this->installingId)
                                                class="pm-install-btn"
                                                style="display:flex;width:100%;align-items:center;justify-content:center;gap:0.375rem;border-radius:0.5rem;border:none;padding:0.375rem 0.75rem;font-size:0.75rem;font-weight:600;color:#fff;box-shadow:0 1px 2px 0 rgba(0,0,0,.05);cursor:pointer;background-color:rgb(var(--color-primary-600,79 70 229));transition:filter .15s"
                                            >
                                                <x-filament::icon
                                                    :icon="$isInstalling ? 'tabler-loader' : 'tabler-download'"
                                                    class="h-3.5 w-3.5 shrink-0 {{ $isInstalling ? 'animate-spin' : '' }}"
                                                />
                                                {{ $isInstalling ? 'Installing…' : 'Install' }}
                                            </button>
                                        @endif

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

                                <div class="flex shrink-0 items-center gap-1.5">
                                    <x-filament::button
                                        icon="tabler-refresh"
                                        color="gray"
                                        size="sm"
                                        outlined
                                        wire:click="update({{ $installed->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="update({{ $installed->id }})"
                                    >Update</x-filament::button>

                                    <x-filament::button
                                        icon="tabler-trash"
                                        color="danger"
                                        size="sm"
                                        outlined
                                        wire:click="uninstall({{ $installed->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="uninstall({{ $installed->id }})"
                                        wire:confirm="Remove '{{ e($installed->name) }}'? This will delete the plugin file from the server."
                                    >Remove</x-filament::button>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

        @endif

    @endif

</x-filament-panels::page>
