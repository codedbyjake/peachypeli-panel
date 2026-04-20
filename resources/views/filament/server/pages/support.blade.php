<x-filament-panels::page>

    @php
        $statusColors = [
            'open'            => 'bg-warning-100 text-warning-700 dark:bg-warning-400/15 dark:text-warning-400',
            'answered'        => 'bg-success-100 text-success-700 dark:bg-success-400/15 dark:text-success-400',
            'customer-reply'  => 'bg-primary-100 text-primary-700 dark:bg-primary-400/15 dark:text-primary-400',
            'closed'          => 'bg-gray-100 text-gray-600 dark:bg-gray-400/15 dark:text-gray-400',
            'on hold'         => 'bg-warning-100 text-warning-700 dark:bg-warning-400/15 dark:text-warning-400',
        ];
        $priorityColors = [
            'high'   => 'bg-danger-100 text-danger-700 dark:bg-danger-400/15 dark:text-danger-400',
            'medium' => 'bg-warning-100 text-warning-700 dark:bg-warning-400/15 dark:text-warning-400',
            'low'    => 'bg-gray-100 text-gray-600 dark:bg-gray-400/15 dark:text-gray-400',
        ];
        $statusClass   = fn($s) => $statusColors[strtolower($s)] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-400/15 dark:text-gray-400';
        $priorityClass = fn($p) => $priorityColors[strtolower($p)] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-400/15 dark:text-gray-400';
    @endphp

    {{-- ── Loading ───────────────────────────────────────────────────────── --}}
    @if ($this->loading)
        <div class="flex items-center justify-center py-16">
            <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
        </div>

    {{-- ── Error / not configured ─────────────────────────────────────────── --}}
    @elseif ($this->error && $this->currentView === 'list')
        <x-filament::section>
            <div class="flex items-center gap-3 text-sm text-danger-600 dark:text-danger-400">
                <x-filament::icon icon="tabler-alert-circle" class="h-5 w-5 shrink-0" />
                <span>{{ $this->error }}</span>
            </div>
        </x-filament::section>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- ── Ticket list ─────────────────────────────────────────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @elseif ($this->currentView === 'list')
        <x-filament::section>
            {{-- Section header --}}
            <x-slot name="heading">
                {{ trans('server/support.title') }}
            </x-slot>

            <x-slot name="afterHeader">
                <div class="flex items-center gap-2">
                    <x-filament::button
                        icon="tabler-refresh"
                        color="gray"
                        size="sm"
                        wire:click="refreshTickets"
                        wire:loading.attr="disabled"
                    >
                        {{ trans('server/support.refresh') }}
                    </x-filament::button>

                    <x-filament::button
                        icon="tabler-plus"
                        color="primary"
                        size="sm"
                        wire:click="showCreate"
                    >
                        {{ trans('server/support.new_ticket') }}
                    </x-filament::button>
                </div>
            </x-slot>

            @if (empty($this->tickets))
                <p class="text-sm text-gray-500 dark:text-gray-400 py-4">
                    {{ trans('server/support.no_tickets') }}
                </p>
            @else
                {{-- Table header --}}
                <div class="hidden sm:grid grid-cols-12 gap-3 px-1 pb-2 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                    <div class="col-span-1">#</div>
                    <div class="col-span-4">Subject</div>
                    <div class="col-span-2">Status</div>
                    <div class="col-span-2">Department</div>
                    <div class="col-span-2">Priority</div>
                    <div class="col-span-1 text-right">Updated</div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->tickets as $t)
                        <button
                            class="w-full text-left grid grid-cols-12 gap-3 px-1 py-3 items-center hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded-lg transition-colors"
                            wire:click="viewTicket({{ (int) $t['id'] }})"
                        >
                            <div class="col-span-1 text-xs font-mono text-gray-400 dark:text-gray-500 truncate">
                                {{ $t['tid'] ?? $t['id'] ?? '—' }}
                            </div>
                            <div class="col-span-4 text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $t['subject'] ?? '—' }}
                            </div>
                            <div class="col-span-2">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $statusClass($t['status'] ?? '') }}">
                                    {{ $t['status'] ?? '—' }}
                                </span>
                            </div>
                            <div class="col-span-2 text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ $t['dept'] ?? $t['department'] ?? '—' }}
                            </div>
                            <div class="col-span-2">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $priorityClass($t['priority'] ?? '') }}">
                                    {{ $t['priority'] ?? '—' }}
                                </span>
                            </div>
                            <div class="col-span-1 text-xs text-gray-400 dark:text-gray-500 text-right whitespace-nowrap">
                                @if (!empty($t['lastreply']))
                                    {{ \Carbon\Carbon::parse($t['lastreply'])->diffForHumans() }}
                                @elseif (!empty($t['date']))
                                    {{ \Carbon\Carbon::parse($t['date'])->diffForHumans() }}
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- ── Ticket detail ──────────────────────────────────────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @elseif ($this->currentView === 'ticket')
        {{-- Breadcrumb / back nav --}}
        <div class="flex items-center gap-2 mb-1">
            <x-filament::button
                icon="tabler-arrow-left"
                color="gray"
                size="sm"
                wire:click="backToList"
            >
                {{ trans('server/support.back_to_tickets') }}
            </x-filament::button>
        </div>

        <x-filament::section>
            {{-- Ticket header --}}
            <x-slot name="heading">
                <div class="flex flex-wrap items-center gap-2">
                    <span>{{ $this->ticket['subject'] ?? 'Ticket' }}</span>
                    <span class="text-xs font-mono font-normal text-gray-400 dark:text-gray-500">
                        #{{ $this->ticket['tid'] ?? $this->ticket['ticketid'] ?? '' }}
                    </span>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $statusClass($this->ticket['status'] ?? '') }}">
                        {{ $this->ticket['status'] ?? '—' }}
                    </span>
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $priorityClass($this->ticket['priority'] ?? '') }}">
                        {{ $this->ticket['priority'] ?? '—' }}
                    </span>
                </div>
            </x-slot>

            {{-- Thread --}}
            <div class="space-y-4">

                {{-- Opening message --}}
                @if (!empty($this->ticket['message']))
                    @php
                        $isAdmin = false;
                        $senderName = user()?->username ?? user()?->email ?? 'You';
                    @endphp
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                            <x-filament::icon icon="tabler-user" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2 mb-1">
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $senderName }}</span>
                                @if (!empty($this->ticket['date']))
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ \Carbon\Carbon::parse($this->ticket['date'])->format('d M Y, H:i') }}
                                    </span>
                                @endif
                            </div>
                            <div class="rounded-xl rounded-tl-none bg-gray-100 dark:bg-gray-800 px-4 py-3 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                                {!! nl2br(e(strip_tags($this->ticket['message']))) !!}
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Replies --}}
                @php
                    $replies = $this->ticket['replies']['reply'] ?? [];
                    if (!empty($replies) && isset($replies['message'])) {
                        $replies = [$replies]; // single reply normalisation
                    }
                @endphp

                @foreach ($replies as $reply)
                    @php
                        $isAdmin   = !empty($reply['admin']) || !empty($reply['adminid']);
                        $name      = $reply['name'] ?? ($isAdmin ? 'Support Team' : (user()?->username ?? 'You'));
                    @endphp
                    <div class="flex gap-3 {{ $isAdmin ? '' : 'flex-row-reverse' }}">
                        <div class="flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center {{ $isAdmin ? 'bg-gray-200 dark:bg-gray-700' : 'bg-primary-100 dark:bg-primary-900/40' }}">
                            <x-filament::icon
                                icon="{{ $isAdmin ? 'tabler-headset' : 'tabler-user' }}"
                                class="h-4 w-4 {{ $isAdmin ? 'text-gray-600 dark:text-gray-400' : 'text-primary-600 dark:text-primary-400' }}"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2 mb-1 {{ $isAdmin ? '' : 'justify-end' }}">
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $name }}</span>
                                @if (!empty($reply['date']))
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ \Carbon\Carbon::parse($reply['date'])->format('d M Y, H:i') }}
                                    </span>
                                @endif
                            </div>
                            <div class="rounded-xl {{ $isAdmin ? 'rounded-tl-none bg-gray-100 dark:bg-gray-800' : 'rounded-tr-none bg-primary-50 dark:bg-primary-900/20' }} px-4 py-3 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                                {!! nl2br(e(strip_tags($reply['message'] ?? ''))) !!}
                            </div>
                        </div>
                    </div>
                @endforeach

            </div>
        </x-filament::section>

        {{-- Reply form --}}
        @if (strtolower($this->ticket['status'] ?? '') !== 'closed')
            <x-filament::section>
                <x-slot name="heading">Reply</x-slot>

                <div class="space-y-4">
                    @error('replyMessage')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror

                    <textarea
                        wire:model="replyMessage"
                        rows="5"
                        placeholder="Type your reply here…"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 resize-y"
                    ></textarea>

                    <div class="flex justify-end">
                        <x-filament::button
                            icon="tabler-send"
                            wire:click="submitReply"
                            wire:loading.attr="disabled"
                        >
                            Send Reply
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">This ticket is closed and cannot receive new replies.</p>
            </x-filament::section>
        @endif

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- ── New ticket form ────────────────────────────────────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @elseif ($this->currentView === 'create')
        <div class="flex items-center gap-2 mb-1">
            <x-filament::button
                icon="tabler-arrow-left"
                color="gray"
                size="sm"
                wire:click="backToList"
            >
                {{ trans('server/support.back_to_tickets') }}
            </x-filament::button>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ trans('server/support.new_ticket') }}</x-slot>

            <div class="space-y-5">

                {{-- Subject --}}
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                    @error('newSubject')
                        <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                    <input
                        type="text"
                        wire:model="newSubject"
                        placeholder="Brief description of your issue"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                    >
                </div>

                {{-- Department + Priority row --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    {{-- Department --}}
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        @error('newDeptId')
                            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                        <select
                            wire:model="newDeptId"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                        >
                            <option value="">Select department…</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept['id'] }}">{{ $dept['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Priority --}}
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                        @error('newPriority')
                            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                        <select
                            wire:model="newPriority"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400"
                        >
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>

                </div>

                {{-- Message --}}
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                    @error('newMessage')
                        <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                    <textarea
                        wire:model="newMessage"
                        rows="7"
                        placeholder="Describe your issue in detail…"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:focus:ring-primary-400 resize-y"
                    ></textarea>
                </div>

                <div class="flex justify-end">
                    <x-filament::button
                        icon="tabler-send"
                        wire:click="submitNewTicket"
                        wire:loading.attr="disabled"
                    >
                        Submit Ticket
                    </x-filament::button>
                </div>

            </div>
        </x-filament::section>

    @endif

</x-filament-panels::page>
