<?php

namespace App\Filament\Server\Widgets;

use App\Enums\SubuserPermission;
use App\Extensions\Tasks\TaskService;
use App\Facades\Activity;
use App\Models\SavedTask;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * @property Schema $form
 * @property Collection<int, SavedTask> $savedTasks
 */
class SavedTasksWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.server.widgets.saved-tasks';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(mixed ...$parameters): void
    {
        $this->server ??= Filament::getTenant();

        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $this->form->fill([
            'action' => array_key_first($taskService->getAll()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $tasks = $taskService->getAll();

        $surrogateSchedule = new Schedule();
        $surrogateSchedule->setRelation('server', $this->server);

        return $schema
            ->statePath('data')
            ->schema([
                TextInput::make('name')
                    ->label('Task Name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Daily restart'),

                Select::make('action')
                    ->label(trans('server/schedule.tasks.actions.title'))
                    ->required()
                    ->live()
                    ->disableOptionWhen(fn (string $value) => !($tasks[$value] ?? null)?->canCreate($surrogateSchedule))
                    ->options($taskService->getMappings())
                    ->selectablePlaceholder(false)
                    ->default(array_key_first($tasks))
                    ->afterStateUpdated(fn ($state, Set $set) => $set('payload', $tasks[$state]?->getDefaultPayload())),

                Group::make(fn (Get $get) => array_key_exists($get('action') ?? '', $tasks)
                    ? $tasks[$get('action')]->getPayloadForm()
                    : []),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SavedTask::create([
            'server_id' => $this->server->id,
            'name'      => $data['name'],
            'action'    => $data['action'],
            'payload'   => $data['payload'] ?? '',
        ]);

        // Invalidate the computed cache so the list refreshes.
        unset($this->savedTasks);

        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $this->form->fill([
            'action' => array_key_first($taskService->getAll()),
        ]);

        Notification::make()
            ->success()
            ->title('Task "' . $data['name'] . '" saved.')
            ->send();
    }

    public function run(int $id): void
    {
        $savedTask = SavedTask::where('server_id', $this->server->id)->findOrFail($id);

        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $schema = $taskService->get($savedTask->action);
        if (!$schema) {
            Notification::make()->danger()->title('Invalid task type.')->send();

            return;
        }

        $task = new Task([
            'action'  => $savedTask->action,
            'payload' => $savedTask->payload ?? '',
        ]);
        $task->setRelation('server', $this->server);

        try {
            $schema->runTask($task);

            Activity::event('server:schedule.run-once')
                ->subject($this->server)
                ->property(['name' => $savedTask->name, 'action' => $savedTask->action, 'payload' => $savedTask->payload ?? ''])
                ->log();

            Notification::make()
                ->success()
                ->title('"' . $savedTask->name . '" executed successfully.')
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Task failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function delete(int $id): void
    {
        SavedTask::where('server_id', $this->server->id)->findOrFail($id)->delete();

        unset($this->savedTasks);

        Notification::make()
            ->success()
            ->title('Task deleted.')
            ->send();
    }

    #[Computed]
    public function savedTasks(): Collection
    {
        return SavedTask::where('server_id', $this->server->id)
            ->orderBy('name')
            ->get();
    }

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState()
            && (user()?->can(SubuserPermission::ScheduleUpdate, $server) ?? false);
    }
}
