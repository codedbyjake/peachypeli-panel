<x-filament::widget>
    <x-filament::section
        heading="Run a Task"
        :description="'Execute a task immediately without saving it as a scheduled task.'"
    >
        <form wire:submit="run" class="flex flex-col gap-6">

            {{ $this->form }}

            <div>
                <x-filament::button
                    type="submit"
                    icon="tabler-player-play"
                    color="primary"
                    wire:loading.attr="disabled"
                >
                    Run Now
                </x-filament::button>
            </div>

        </form>
    </x-filament::section>
</x-filament::widget>
