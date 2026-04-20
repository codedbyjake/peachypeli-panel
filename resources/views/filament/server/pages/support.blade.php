<x-filament-panels::page>

    @php
        $statusColor = function(string $s): string {
            return match (strtolower($s)) {
                'open'            => 'warning',
                'answered'        => 'success',
                'customer-reply'  => 'primary',
                'on hold'         => 'warning',
                'closed'          => 'gray',
                default           => 'gray',
            };
        };
        $priorityColor = function(string $p): string {
            return match (strtolower($p)) {
                'high'   => 'danger',
                'medium' => 'warning',
                'low'    => 'gray',
                default  => 'gray',
            };
        };
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
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:48px 0;text-align:center;">
                    <x-filament::icon icon="tabler-ticket" style="width:2.5rem;height:2.5rem;color:var(--gray-300);" />
                    <p style="font-size:0.875rem;color:var(--gray-500);">{{ trans('server/support.no_tickets') }}</p>
                </div>
            @else
                {{-- Column headers --}}
                <div style="display:grid;grid-template-columns:80px 1fr 120px 140px 90px 100px;gap:12px;padding:0 4px 10px;border-bottom:1px solid var(--gray-200);margin-bottom:4px;">
                    <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);">#</span>
                    <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);">Subject</span>
                    <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);">Status</span>
                    <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);">Department</span>
                    <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);">Priority</span>
                    <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);text-align:right;">Updated</span>
                </div>

                {{-- Ticket rows --}}
                @foreach ($this->tickets as $t)
                    <button
                        wire:click="viewTicket({{ (int) ($t['id'] ?? 0) }})"
                        style="display:grid;grid-template-columns:80px 1fr 120px 140px 90px 100px;gap:12px;width:100%;text-align:left;padding:10px 4px;align-items:center;border-radius:8px;border:none;background:transparent;cursor:pointer;transition:background 150ms ease;"
                        onmouseover="this.style.background='var(--gray-50)'"
                        onmouseout="this.style.background='transparent'"
                    >
                        {{-- Ticket ref --}}
                        <span style="font-size:0.75rem;font-family:monospace;color:var(--gray-400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $t['tid'] ?? $t['id'] ?? '—' }}
                        </span>

                        {{-- Subject --}}
                        <span style="font-size:0.875rem;font-weight:500;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $t['subject'] ?? '—' }}
                        </span>

                        {{-- Status badge --}}
                        <div>
                            <x-filament::badge :color="$statusColor($t['status'] ?? '')">
                                {{ ucfirst($t['status'] ?? '—') }}
                            </x-filament::badge>
                        </div>

                        {{-- Department --}}
                        <span style="font-size:0.8rem;color:var(--gray-500);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $t['dept'] ?? $t['department'] ?? '—' }}
                        </span>

                        {{-- Priority badge --}}
                        <div>
                            <x-filament::badge :color="$priorityColor($t['priority'] ?? '')">
                                {{ ucfirst($t['priority'] ?? '—') }}
                            </x-filament::badge>
                        </div>

                        {{-- Last updated --}}
                        <span style="font-size:0.75rem;color:var(--gray-400);text-align:right;white-space:nowrap;">
                            @if (!empty($t['lastreply']))
                                {{ \Carbon\Carbon::parse($t['lastreply'])->diffForHumans() }}
                            @elseif (!empty($t['date']))
                                {{ \Carbon\Carbon::parse($t['date'])->diffForHumans() }}
                            @else
                                —
                            @endif
                        </span>
                    </button>

                    @if (!$loop->last)
                        <div style="border-bottom:1px solid var(--gray-100);margin:0 4px;"></div>
                    @endif
                @endforeach
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

            <x-slot name="afterHeader">
                <div style="display:flex;align-items:center;gap:8px;">
                    <x-filament::badge :color="$statusColor($this->ticket['status'] ?? '')">
                        {{ ucfirst($this->ticket['status'] ?? '—') }}
                    </x-filament::badge>
                    <x-filament::badge :color="$priorityColor($this->ticket['priority'] ?? '')">
                        {{ ucfirst($this->ticket['priority'] ?? '—') }}
                    </x-filament::badge>
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
                            @php
                                $raw = $this->ticket['attachments']['attachment'] ?? [];
                                // Normalise single attachment (assoc) to a list
                                $openingAttachments = isset($raw['filename']) ? [$raw] : (array) $raw;
                                // Only keep entries that have a real HTTP URL
                                $openingAttachments = array_filter($openingAttachments, fn($a) =>
                                    is_array($a) &&
                                    !empty($a['filename']) &&
                                    !empty($a['url']) &&
                                    str_starts_with($a['url'], 'http')
                                );
                            @endphp
                            @if (!empty($openingAttachments))
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                                    @foreach ($openingAttachments as $att)
                                        <a href="{{ $att['url'] }}" target="_blank" rel="noopener"
                                            style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;border:1px solid var(--gray-200);font-size:0.75rem;color:var(--gray-600);text-decoration:none;background:var(--white,#fff);"
                                            onmouseover="this.style.background='var(--gray-50)'"
                                            onmouseout="this.style.background='var(--white,#fff)'"
                                        ><x-filament::icon icon="tabler-paperclip" style="width:0.875rem;height:0.875rem;" />{{ $att['filename'] }}</a>
                                    @endforeach
                                </div>
                            @endif
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
                            @php
                                $rawReply = $reply['attachments']['attachment'] ?? [];
                                // Normalise single attachment (assoc) to a list
                                $replyAtts = isset($rawReply['filename']) ? [$rawReply] : (array) $rawReply;
                                // Only keep entries that have a real HTTP URL
                                $replyAtts = array_filter($replyAtts, fn($a) =>
                                    is_array($a) &&
                                    !empty($a['filename']) &&
                                    !empty($a['url']) &&
                                    str_starts_with($a['url'], 'http')
                                );
                            @endphp
                            @if (!empty($replyAtts))
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;{{ $isAdmin ? '' : 'justify-content:flex-end;' }}">
                                    @foreach ($replyAtts as $att)
                                        <a href="{{ $att['url'] }}" target="_blank" rel="noopener"
                                            style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;border:1px solid var(--gray-200);font-size:0.75rem;color:var(--gray-600);text-decoration:none;background:var(--white,#fff);"
                                            onmouseover="this.style.background='var(--gray-50)'"
                                            onmouseout="this.style.background='var(--white,#fff)'"
                                        ><x-filament::icon icon="tabler-paperclip" style="width:0.875rem;height:0.875rem;" />{{ $att['filename'] }}</a>
                                    @endforeach
                                </div>
                            @endif
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

                    {{-- Attachments --}}
                    <div class="space-y-1">
                        <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">Attachments <span style="font-weight:400;color:var(--gray-400);">(optional, max 10 MB each)</span></label>
                        @error('replyAttachments.*')
                            <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                        @enderror
                        <input
                            type="file"
                            wire:model="replyAttachments"
                            multiple
                            style="display:block;width:100%;padding:6px 10px;border-radius:8px;border:1px solid var(--gray-300);background:var(--white,#fff);font-size:0.875rem;color:var(--gray-700);cursor:pointer;"
                        >
                        @if (!empty($replyAttachments))
                            <p style="font-size:0.75rem;color:var(--gray-500);">{{ count($replyAttachments) }} file(s) selected</p>
                        @endif
                    </div>

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

                {{-- Attachments --}}
                <div class="space-y-1">
                    <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">Attachments <span style="font-weight:400;color:var(--gray-400);">(optional, max 10 MB each)</span></label>
                    @error('newAttachments.*')
                        <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                    @enderror
                    <input
                        type="file"
                        wire:model="newAttachments"
                        multiple
                        style="display:block;width:100%;padding:6px 10px;border-radius:8px;border:1px solid var(--gray-300);background:var(--white,#fff);font-size:0.875rem;color:var(--gray-700);cursor:pointer;"
                    >
                    @if (!empty($newAttachments))
                        <p style="font-size:0.75rem;color:var(--gray-500);">{{ count($newAttachments) }} file(s) selected</p>
                    @endif
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
