<?php

namespace App\Filament\Server\Widgets;

use App\Enums\SubuserPermission;
use App\Extensions\Tasks\TaskService;
use App\Facades\Activity;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

/**
 * @property Schema $form
 */
class RunTaskWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.server.widgets.run-task';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(mixed ...$parameters): void
    {
        // Fall back to the Filament tenant when the page does not pass $server explicitly.
        $this->server ??= Filament::getTenant();

        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $tasks = $taskService->getAll();

        $this->form->fill([
            'action' => array_key_first($tasks),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $tasks = $taskService->getAll();

        // A surrogate schedule is needed so canCreate() checks (e.g. backup limit)
        // have access to the server without requiring a real DB record.
        $surrogateSchedule = new Schedule();
        $surrogateSchedule->setRelation('server', $this->server);

        return $schema
            ->statePath('data')
            ->schema([
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

    public function run(): void
    {
        $data = $this->form->getState();

        /** @var TaskService $taskService */
        $taskService = app(TaskService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $schema = $taskService->get($data['action']);
        if (!$schema) {
            Notification::make()->danger()->title('Invalid task type.')->send();

            return;
        }

        // Build a synthetic Task with the server pre-loaded. All task schemas only
        // access $task->server and $task->payload, so no DB record is required.
        $task = new Task([
            'action'  => $data['action'],
            'payload' => $data['payload'] ?? '',
        ]);
        $task->setRelation('server', $this->server);

        try {
            $schema->runTask($task);

            Activity::event('server:schedule.run-once')
                ->subject($this->server)
                ->property(['action' => $data['action'], 'payload' => $data['payload'] ?? ''])
                ->log();

            Notification::make()
                ->success()
                ->title($schema->getName() . ' executed successfully.')
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Task failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState()
            && (user()?->can(SubuserPermission::ScheduleUpdate, $server) ?? false);
    }
}
