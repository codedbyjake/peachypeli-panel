<?php

namespace App\Filament\Server\Resources\Allocations\Pages;

use App\Filament\Server\Resources\Allocations\AllocationResource;
use App\Models\Server;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListAllocations extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = AllocationResource::class;

    public function mount(): void
    {
        parent::mount();

        /** @var Server $server */
        $server = Filament::getTenant();

        $this->autoLabelAllocations($server);
    }

    private function autoLabelAllocations(Server $server): void
    {
        $allocations = $server->allocations()->get();
        if ($allocations->isEmpty()) {
            return;
        }

        // Build port → human-readable label from egg variables whose value is a port number.
        $portLabels = [];
        foreach ($server->variables ?? [] as $variable) {
            $value = trim((string) ($variable->server_value ?? $variable->default_value ?? ''));
            if ($value === '' || !ctype_digit($value)) {
                continue;
            }
            $port = (int) $value;
            if ($port < 1 || $port > 65535) {
                continue;
            }
            $label = ucwords(strtolower(str_replace('_', ' ', $variable->env_variable ?? '')));
            // Strip trailing " Port" duplicate if the variable name already says "Port"
            $label = preg_replace('/\bPort Port\b/i', 'Port', $label);
            $portLabels[$port] = $label;
        }

        foreach ($allocations as $allocation) {
            // Skip if a note already exists.
            if (!empty($allocation->notes)) {
                continue;
            }

            // Primary allocation is always "Game Port".
            if ($allocation->id === $server->allocation_id) {
                $allocation->update(['notes' => 'Game Port']);
                continue;
            }

            $label = $portLabels[$allocation->port] ?? null;
            if ($label !== null) {
                $allocation->update(['notes' => $label]);
            }
        }
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return trans('server/network.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/network.title');
    }
}
