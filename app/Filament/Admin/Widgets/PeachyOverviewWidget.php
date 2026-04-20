<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PeachyOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Servers', Server::count()),
            Stat::make('Total Users', User::count()),
            Stat::make('Total Nodes', Node::count()),
            Stat::make('Total Eggs', Egg::count()),
        ];
    }
}
