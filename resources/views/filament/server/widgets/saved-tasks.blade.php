<x-filament::widget>
    <x-filament::section
        heading="Saved Tasks"
        :description="'Save frequently used tasks for quick access and reuse.'"
    >
        {{-- Create / save form --}}
        <form wire:submit="save" class="flex flex-col gap-6">

            {{ $this->form }}

            <div>
                <x-filament::button
                    type="submit"
                    icon="tabler-device-floppy"
                    color="primary"
                    wire:loading.attr="disabled"
                >
                    Save Task
                </x-filament::button>
            </div>

        </form>

        {{-- Saved task list --}}
        @if ($this->savedTasks->isNotEmpty())

            <div class="mt-6 border-t border-gray-100 dark:border-gray-700 pt-4">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->savedTasks as $savedTask)
                        @php
                            $taskService = app(\App\Extensions\Tasks\TaskService::class);
                            $schema      = $taskService->get($savedTask->action);
                            $actionLabel = $schema?->getName() ?? $savedTask->action;
                            $payloadDisplay = $savedTask->payload
                                ? ($schema?->formatPayload($savedTask->payload) ?? $savedTask->payload)
                                : null;
                            if (is_array($payloadDisplay)) {
                                $payloadDisplay = implode(', ', $payloadDisplay);
                            }
                        @endphp
                        <div class="flex items-center justify-between gap-4 py-3 px-1 first:pt-1 last:pb-1">

                            {{-- Task info --}}
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $savedTask->name }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ $actionLabel }}@if ($payloadDisplay)&nbsp;&mdash; {{ $payloadDisplay }}@endif
                                </p>
                            </div>

                            {{-- Actions --}}
                            <div class="flex shrink-0 items-center gap-2">
                                <x-filament::button
                                    icon="tabler-player-play"
                                    color="primary"
                                    size="sm"
                                    wire:click="run({{ $savedTask->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="run({{ $savedTask->id }})"
                                >
                                    Run
                                </x-filament::button>

                                <x-filament::button
                                    icon="tabler-trash"
                                    color="danger"
                                    size="sm"
                                    outlined
                                    wire:click="delete({{ $savedTask->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="delete({{ $savedTask->id }})"
                                    wire:confirm="Delete '{{ e($savedTask->name) }}'?"
                                >
                                    Delete
                                </x-filament::button>
                            </div>

                        </div>
                    @endforeach
                </div>
            </div>

        @endif

    </x-filament::section>
</x-filament::widget>
