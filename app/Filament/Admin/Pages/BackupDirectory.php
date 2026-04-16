<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use App\Models\Server;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BackupDirectory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Archive;

    protected string $view = 'filament.pages.backup-directory';

    public function getTitle(): string
    {
        return 'Backup Directory';
    }

    public static function getNavigationLabel(): string
    {
        return 'Backup Directory';
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Server::query()
                ->with(['user', 'backups'])
                ->withCount('backups'))
            ->columns([
                TextColumn::make('uuid')
                    ->label('Server UUID')
                    ->copyable()
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Server Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Owner Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('backups_count')
                    ->label('Backups')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewBackups')
                    ->label('View Backups')
                    ->icon(TablerIcon::Archive)
                    ->iconButton()
                    ->tooltip('View Backup UUIDs')
                    ->schema([
                        RepeatableEntry::make('backups')
                            ->hiddenLabel()
                            ->placeholder('No backups found for this server.')
                            ->table([
                                TableColumn::make('Backup UUID'),
                                TableColumn::make('Name'),
                                TableColumn::make('Created'),
                            ])
                            ->schema([
                                TextEntry::make('uuid')
                                    ->hiddenLabel()
                                    ->copyable()
                                    ->fontFamily(FontFamily::Mono),
                                TextEntry::make('name')
                                    ->hiddenLabel(),
                                TextEntry::make('created_at')
                                    ->hiddenLabel()
                                    ->since(),
                            ]),
                    ])
                    ->modalHeading(fn (Server $record) => "Backups: {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateIcon(TablerIcon::Archive)
            ->emptyStateHeading('No servers found');
    }
}
