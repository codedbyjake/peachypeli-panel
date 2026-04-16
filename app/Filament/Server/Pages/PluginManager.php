<?php

namespace App\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Extensions\Plugins\PluginService;
use App\Extensions\Plugins\Sources\CurseForgeSource;
use App\Models\InstalledPlugin;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * @property Collection<int, InstalledPlugin> $installedPlugins
 */
class PluginManager extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.server.pages.plugin-manager';

    public ?Server $server = null;

    public string $search = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    /** ID of the plugin currently being installed — used to show per-card loading state. */
    public ?string $installingId = null;

    public string $activeTab = 'browse';

    public string $gameType = 'unknown';

    public string $sourceName = '';

    public bool $supported = false;

    public bool $needsCurseForgeKey = false;

    protected PluginService $pluginService;

    public function boot(PluginService $pluginService): void
    {
        $this->pluginService = $pluginService;
    }

    public function mount(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        $this->server = $server;

        $this->gameType = $this->pluginService->detectGameType($server);
        $source         = $this->pluginService->resolveSource($server);

        if ($source) {
            $this->supported  = true;
            $this->sourceName = $source->getName();

            if ($source instanceof CurseForgeSource && !$source->hasApiKey()) {
                $this->needsCurseForgeKey = true;
                $this->supported          = false;
            } else {
                $this->loadFeatured($source);
            }
        }
    }

    private function loadFeatured(PluginSourceInterface $source): void
    {
        try {
            $this->results = $source->getFeatured();
        } catch (Exception) {
            $this->results = [];
        }
    }

    public function searchPlugins(): void
    {
        $source = $this->pluginService->resolveSource($this->server);
        if (!$source) {
            return;
        }

        $query = trim($this->search);

        try {
            if ($query === '') {
                $this->results = $source->getFeatured();
            } else {
                $this->results = $source->search($query);
            }
        } catch (Exception $e) {
            $this->results = [];
            Notification::make()->danger()->title('Search failed')->body($e->getMessage())->send();
        }
    }

    public function install(string $pluginId): void
    {
        // Look up the full plugin data from the current results — avoids passing URLs
        // through HTML attributes, which breaks due to @js() producing double-quoted strings.
        $plugin = collect($this->results)->firstWhere('id', $pluginId);

        if (!$plugin) {
            Notification::make()->danger()->title('Plugin not found. Try refreshing the page.')->send();

            return;
        }

        $this->installingId = $pluginId;

        try {
            $source     = $this->pluginService->resolveSource($this->server);
            $installDir = $source?->getInstallDir() ?? '/plugins';
            $pluginName = $plugin['name'] ?? $pluginId;
            $version    = $plugin['version'] ?? 'unknown';
            $fileName   = $plugin['file_name'] ?? null;
            $downloadUrl = $plugin['download_url'] ?? null;

            // Sources that resolve the download URL lazily (e.g. Modrinth): fetch it now.
            if (!$downloadUrl) {
                $versionInfo = $source?->getLatestVersion($pluginId);

                if (!$versionInfo || empty($versionInfo['download_url'])) {
                    Notification::make()->danger()->title("Could not resolve download URL for '{$pluginName}'.")->send();

                    return;
                }

                $downloadUrl = $versionInfo['download_url'];
                $fileName    = $versionInfo['file_name'] ?? $fileName;
                $version     = $versionInfo['version'] ?? $version;
            }

            // Ensure we always have a filename — derive from the URL if nothing else.
            if (!$fileName) {
                $fileName = basename(parse_url($downloadUrl, PHP_URL_PATH) ?? $pluginId);
            }

            /** @var DaemonFileRepository $fileRepo */
            $fileRepo = (new DaemonFileRepository())->setServer($this->server);

            // Pull the file directly to the server. foreground=true makes Wings wait until
            // the download is complete before responding, so we know it succeeded.
            $fileRepo->pull($downloadUrl, $installDir, ['filename' => $fileName, 'foreground' => true]);

            // Thunderstore packages are zip archives — extract then remove the zip.
            if ($source?->isArchive()) {
                $fileRepo->decompressFile($installDir, $fileName);
                $fileRepo->deleteFiles($installDir, [$fileName]);
            }

            InstalledPlugin::create([
                'server_id'   => $this->server->id,
                'source'      => $source?->getSlug() ?? 'unknown',
                'plugin_id'   => $pluginId,
                'name'        => $pluginName,
                'version'     => $version,
                'file_name'   => $source?->isArchive() ? '' : $fileName,
                'install_dir' => $installDir,
            ]);

            unset($this->installedPlugins);

            Notification::make()->success()->title("'{$pluginName}' installed successfully.")->send();
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Plugin install failed', [
                'server_id' => $this->server->id,
                'plugin_id' => $pluginId,
                'error'     => $e->getMessage(),
            ]);
            Notification::make()->danger()->title('Install failed')->body($e->getMessage())->send();
        } finally {
            $this->installingId = null;
        }
    }

    public function uninstall(int $installedPluginId): void
    {
        $plugin = InstalledPlugin::where('server_id', $this->server->id)->findOrFail($installedPluginId);

        try {
            if ($plugin->file_name) {
                /** @var DaemonFileRepository $fileRepo */
                $fileRepo = (new DaemonFileRepository())->setServer($this->server);
                $fileRepo->deleteFiles($plugin->install_dir, [$plugin->file_name]);
            }

            $plugin->delete();

            unset($this->installedPlugins);

            Notification::make()->success()->title("'{$plugin->name}' removed.")->send();
        } catch (Exception $e) {
            Notification::make()->danger()->title('Uninstall failed')->body($e->getMessage())->send();
        }
    }

    public function update(int $installedPluginId): void
    {
        $plugin = InstalledPlugin::where('server_id', $this->server->id)->findOrFail($installedPluginId);

        try {
            $source = $this->pluginService->resolveSource($this->server);
            if (!$source) {
                return;
            }

            $versionInfo = $source->getLatestVersion($plugin->plugin_id);
            if (!$versionInfo || !($versionInfo['download_url'] ?? null)) {
                Notification::make()->warning()->title('No update available or could not reach source.')->send();

                return;
            }

            if ($versionInfo['version'] === $plugin->version) {
                Notification::make()->info()->title("'{$plugin->name}' is already up to date ({$plugin->version}).")->send();

                return;
            }

            /** @var DaemonFileRepository $fileRepo */
            $fileRepo = (new DaemonFileRepository())->setServer($this->server);

            // Delete old file if we know its name.
            if ($plugin->file_name) {
                $fileRepo->deleteFiles($plugin->install_dir, [$plugin->file_name]);
            }

            $newFileName = $versionInfo['file_name'] ?? $plugin->file_name;

            $fileRepo->pull($versionInfo['download_url'], $plugin->install_dir, ['filename' => $newFileName]);

            if ($source->isArchive()) {
                $fileRepo->decompressFile($plugin->install_dir, $newFileName);
                $fileRepo->deleteFiles($plugin->install_dir, [$newFileName]);
            }

            $plugin->update([
                'version'   => $versionInfo['version'],
                'file_name' => $source->isArchive() ? '' : $newFileName,
            ]);

            unset($this->installedPlugins);

            Notification::make()->success()->title("'{$plugin->name}' updated to {$versionInfo['version']}.")->send();
        } catch (Exception $e) {
            Notification::make()->danger()->title('Update failed')->body($e->getMessage())->send();
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function isInstalled(string $pluginId): bool
    {
        return $this->installedPlugins->where('plugin_id', $pluginId)->isNotEmpty();
    }

    #[Computed]
    public function installedPlugins(): Collection
    {
        return InstalledPlugin::where('server_id', $this->server->id)
            ->orderBy('name')
            ->get();
    }

    public function getInstallDir(): string
    {
        return $this->pluginService->resolveSource($this->server)?->getInstallDir() ?? '/plugins';
    }

    public static function canAccess(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState()
            && (user()?->can(SubuserPermission::FileCreate, $server) ?? false);
    }

    public static function getNavigationLabel(): string
    {
        return 'Plugins';
    }

    public function getTitle(): string
    {
        return 'Plugin Manager';
    }
}
