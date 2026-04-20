<x-filament-panels::page>

    @php
        $statusColor = function(string $s): string {
            return match (strtolower($s)) {
                'open'           => 'warning',
                'answered'       => 'success',
                'customer-reply' => 'primary',
                'on hold'        => 'warning',
                'closed'         => 'gray',
                default          => 'gray',
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

    <style>
        .sp-input,.sp-textarea,.sp-select {
            width:100%;border-radius:8px;border:1px solid var(--gray-300);
            background:var(--gray-50);padding:8px 12px;font-size:0.875rem;
            color:var(--gray-900);outline:none;box-sizing:border-box;
            transition:border-color 150ms,box-shadow 150ms;
        }
        .sp-textarea { resize:vertical;font-family:inherit;line-height:1.5; }
        .sp-input:focus,.sp-textarea:focus,.sp-select:focus {
            border-color:var(--primary-500);
            box-shadow:0 0 0 2px var(--primary-100);
        }
        .sp-select { appearance:none;-webkit-appearance:none;cursor:pointer; }
        .sp-tab { background:transparent;border-left:none;border-right:none;border-top:none;cursor:pointer;transition:color 150ms; }
        .sp-tab-inactive { color:var(--gray-500); }
        .sp-tab-inactive:hover { color:var(--gray-700); }
        .sp-ticket-row { border:none;background:transparent;cursor:pointer;text-align:left;width:100%; }
        .sp-ticket-row:hover { background:var(--gray-50); }
        .sp-att-link { text-decoration:none;transition:background 150ms; }
        .sp-att-link:hover { background:var(--gray-100) !important; }
        .sp-file-input { display:block;width:100%;padding:6px 10px;border-radius:8px;border:1px solid var(--gray-300);background:var(--gray-50);font-size:0.875rem;color:var(--gray-700);cursor:pointer;box-sizing:border-box; }
    </style>

    {{-- ── Loading ────────────────────────────────────────────────────────── --}}
    @if ($this->loading)
        <div style="display:flex;align-items:center;justify-content:center;padding:64px 0;">
            <x-filament::loading-indicator style="height:2rem;width:2rem;color:var(--primary-500);" />
        </div>

    {{-- ── Error / not configured ──────────────────────────────────────────── --}}
    @elseif ($this->error && $this->currentView === 'list')
        <x-filament::section>
            <div style="display:flex;align-items:center;gap:12px;font-size:0.875rem;color:var(--danger-600);">
                <x-filament::icon icon="tabler-alert-circle" style="height:1.25rem;width:1.25rem;flex-shrink:0;" />
                <span>{{ $this->error }}</span>
            </div>
        </x-filament::section>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- ── Ticket list ─────────────────────────────────────────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @elseif ($this->currentView === 'list')

        <x-filament::section>
            <x-slot name="heading">{{ trans('server/support.title') }}</x-slot>

            <x-slot name="afterHeader">
                <div style="display:flex;align-items:center;gap:8px;">
                    <x-filament::button icon="tabler-refresh" color="gray" size="sm"
                        wire:click="refreshTickets" wire:loading.attr="disabled">
                        {{ trans('server/support.refresh') }}
                    </x-filament::button>
                    <x-filament::button icon="tabler-plus" color="primary" size="sm"
                        wire:click="showCreate">
                        {{ trans('server/support.new_ticket') }}
                    </x-filament::button>
                </div>
            </x-slot>

            @php
                $openTickets   = array_values(array_filter($this->tickets, fn($t) => strtolower($t['status'] ?? '') !== 'closed'));
                $closedTickets = array_values(array_filter($this->tickets, fn($t) => strtolower($t['status'] ?? '') === 'closed'));
                $tabTickets    = $this->ticketTab === 'open' ? $openTickets : $closedTickets;
            @endphp

            {{-- Tab bar --}}
            <div style="display:flex;align-items:center;border-bottom:1px solid var(--gray-200);margin-bottom:16px;">
                <button wire:click="setTicketTab('open')"
                    class="sp-tab {{ $this->ticketTab === 'open' ? 'sp-tab-active' : 'sp-tab-inactive' }}"
                    style="padding:10px 16px;font-size:0.875rem;font-weight:500;margin-bottom:-1px;{{ $this->ticketTab === 'open' ? 'border-bottom:2px solid var(--primary-600);color:var(--primary-600);' : 'border-bottom:2px solid transparent;' }}">
                    {{ 'Open' . (!empty($openTickets) ? ' (' . count($openTickets) . ')' : '') }}
                </button>
                <button wire:click="setTicketTab('closed')"
                    class="sp-tab {{ $this->ticketTab === 'closed' ? 'sp-tab-active' : 'sp-tab-inactive' }}"
                    style="padding:10px 16px;font-size:0.875rem;font-weight:500;margin-bottom:-1px;{{ $this->ticketTab === 'closed' ? 'border-bottom:2px solid var(--primary-600);color:var(--primary-600);' : 'border-bottom:2px solid transparent;' }}">
                    {{ 'Closed' . (!empty($closedTickets) ? ' (' . count($closedTickets) . ')' : '') }}
                </button>
            </div>

            {{-- Ticket table --}}
            @if (empty($tabTickets))
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:48px 0;text-align:center;">
                    <x-filament::icon icon="tabler-ticket" style="width:2.5rem;height:2.5rem;color:var(--gray-300);" />
                    <p style="font-size:0.875rem;color:var(--gray-500);">
                        {{ $this->ticketTab === 'open' ? trans('server/support.no_tickets') : 'No closed tickets.' }}
                    </p>
                </div>
            @else
                <div style="display:grid;grid-template-columns:80px 1fr 130px 140px 90px 100px;gap:12px;padding:0 4px 10px;border-bottom:1px solid var(--gray-200);margin-bottom:4px;">
                    @foreach (['#', 'Subject', 'Status', 'Department', 'Priority', 'Updated'] as $col)
                        <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);{{ $col === 'Updated' ? 'text-align:right;' : '' }}">{{ $col }}</span>
                    @endforeach
                </div>

                @foreach ($tabTickets as $t)
                    <button class="sp-ticket-row"
                        wire:click="viewTicket({{ (int) ($t['id'] ?? 0) }})"
                        style="display:grid;grid-template-columns:80px 1fr 130px 140px 90px 100px;gap:12px;padding:10px 4px;align-items:center;border-radius:8px;transition:background 150ms;">
                        <span style="font-size:0.75rem;font-family:monospace;color:var(--gray-400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $t['tid'] ?? $t['id'] ?? '—' }}</span>
                        <span style="font-size:0.875rem;font-weight:500;color:var(--gray-900);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $t['subject'] ?? '—' }}</span>
                        <div><x-filament::badge :color="$statusColor($t['status'] ?? '')">{{ ucfirst($t['status'] ?? '—') }}</x-filament::badge></div>
                        <span style="font-size:0.8rem;color:var(--gray-500);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $t['dept'] ?? $t['department'] ?? '—' }}</span>
                        <div><x-filament::badge :color="$priorityColor($t['priority'] ?? '')">{{ ucfirst($t['priority'] ?? '—') }}</x-filament::badge></div>
                        <span style="font-size:0.75rem;color:var(--gray-400);text-align:right;white-space:nowrap;">
                            @if (!empty($t['lastreply'])){{ \Carbon\Carbon::parse($t['lastreply'])->diffForHumans() }}
                            @elseif (!empty($t['date'])){{ \Carbon\Carbon::parse($t['date'])->diffForHumans() }}
                            @else —
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

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <x-filament::button icon="tabler-arrow-left" color="gray" size="sm" wire:click="backToList">
                {{ trans('server/support.back_to_tickets') }}
            </x-filament::button>
        </div>

        <x-filament::section>
            <x-slot name="heading">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    <span>{{ $this->ticket['subject'] ?? 'Ticket' }}</span>
                    <span style="font-size:0.75rem;font-family:monospace;font-weight:400;color:var(--gray-400);">
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
                    @if (strtolower($this->ticket['status'] ?? '') !== 'closed')
                        <x-filament::button color="danger" size="sm" icon="tabler-x"
                            wire:click="closeTicket" wire:loading.attr="disabled">
                            Close Ticket
                        </x-filament::button>
                    @endif
                </div>
            </x-slot>

            {{-- Message thread --}}
            <div style="display:flex;flex-direction:column;gap:16px;">

                {{-- Opening message --}}
                @if (!empty($this->ticket['message']))
                    @php
                        $senderName = user()?->username ?? user()?->email ?? 'You';
                    @endphp
                    <div style="display:flex;gap:12px;">
                        <div style="flex-shrink:0;height:2rem;width:2rem;border-radius:9999px;background:var(--primary-100);display:flex;align-items:center;justify-content:center;">
                            <x-filament::icon icon="tabler-user" style="height:1rem;width:1rem;color:var(--primary-600);" />
                        </div>
                        <div style="flex:1 1 0%;min-width:0;">
                            <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:4px;">
                                <span style="font-size:0.875rem;font-weight:600;color:var(--gray-900);">{{ $senderName }}</span>
                                @if (!empty($this->ticket['date']))
                                    <span style="font-size:0.75rem;color:var(--gray-400);">
                                        {{ \Carbon\Carbon::parse($this->ticket['date'])->format('d M Y, H:i') }}
                                    </span>
                                @endif
                            </div>
                            <div style="border-radius:12px 12px 12px 0;background:var(--gray-100);padding:12px 16px;font-size:0.875rem;color:var(--gray-800);white-space:pre-wrap;word-break:break-word;">
                                {!! $this->linkify($this->ticket['message'] ?? '') !!}
                            </div>
                            @php
                                $whmcsBase = rtrim(config('services.whmcs.url', ''), '/');
                                $ticketC   = $this->ticket['c'] ?? '';
                                $raw = $this->ticket['attachments']['attachment'] ?? [];
                                $openingAttachments = isset($raw['filename']) ? [$raw] : (array) $raw;
                                $openingAttachments = array_filter($openingAttachments, fn($a) =>
                                    is_array($a) && !empty($a['filename']) && isset($a['index'])
                                );
                            @endphp
                            @if (!empty($openingAttachments))
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                                    @foreach ($openingAttachments as $att)
                                        @php
                                            $attUrl = $whmcsBase . '/downloads/ticket/' . $ticketC . '/' . $att['index'] . '/' . rawurlencode($att['filename']);
                                        @endphp
                                        <a href="{{ $attUrl }}" target="_blank" rel="noopener" class="sp-att-link"
                                            style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;border:1px solid var(--gray-200);font-size:0.75rem;color:var(--gray-600);background:transparent;">
                                            <x-filament::icon icon="tabler-paperclip" style="width:0.875rem;height:0.875rem;" />
                                            {{ $att['filename'] }}
                                        </a>
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
                        $replies = [$replies];
                    }
                @endphp

                @foreach ($replies as $reply)
                    @php
                        $isAdmin = !empty($reply['admin']) || !empty($reply['adminid']);
                        $name    = $reply['name'] ?? ($isAdmin ? 'Support Team' : (user()?->username ?? 'You'));
                    @endphp
                    <div style="display:flex;gap:12px;{{ $isAdmin ? '' : 'flex-direction:row-reverse;' }}">
                        <div style="flex-shrink:0;height:2rem;width:2rem;border-radius:9999px;{{ $isAdmin ? 'background:var(--gray-200);' : 'background:var(--primary-100);' }}display:flex;align-items:center;justify-content:center;">
                            <x-filament::icon
                                icon="{{ $isAdmin ? 'tabler-headset' : 'tabler-user' }}"
                                style="height:1rem;width:1rem;color:{{ $isAdmin ? 'var(--gray-600)' : 'var(--primary-600)' }};"
                            />
                        </div>
                        <div style="flex:1 1 0%;min-width:0;">
                            <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:4px;{{ $isAdmin ? '' : 'justify-content:flex-end;' }}">
                                <span style="font-size:0.875rem;font-weight:600;color:var(--gray-900);">{{ $name }}</span>
                                @if (!empty($reply['date']))
                                    <span style="font-size:0.75rem;color:var(--gray-400);">
                                        {{ \Carbon\Carbon::parse($reply['date'])->format('d M Y, H:i') }}
                                    </span>
                                @endif
                            </div>
                            <div style="{{ $isAdmin ? 'border-radius:12px 12px 12px 0;background:var(--gray-100);' : 'border-radius:12px 12px 0 12px;background:var(--primary-50);' }}padding:12px 16px;font-size:0.875rem;color:var(--gray-800);white-space:pre-wrap;word-break:break-word;">
                                {!! $this->linkify($reply['message'] ?? '') !!}
                            </div>
                            @php
                                $rawReply = $reply['attachments']['attachment'] ?? [];
                                $replyAtts = isset($rawReply['filename']) ? [$rawReply] : (array) $rawReply;
                                $replyAtts = array_filter($replyAtts, fn($a) =>
                                    is_array($a) && !empty($a['filename']) && isset($a['index'])
                                );
                            @endphp
                            @if (!empty($replyAtts))
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;{{ $isAdmin ? '' : 'justify-content:flex-end;' }}">
                                    @foreach ($replyAtts as $att)
                                        @php
                                            $attUrl = $whmcsBase . '/downloads/ticket/' . $ticketC . '/' . $att['index'] . '/' . rawurlencode($att['filename']);
                                        @endphp
                                        <a href="{{ $attUrl }}" target="_blank" rel="noopener" class="sp-att-link"
                                            style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;border:1px solid var(--gray-200);font-size:0.75rem;color:var(--gray-600);background:transparent;">
                                            <x-filament::icon icon="tabler-paperclip" style="width:0.875rem;height:0.875rem;" />
                                            {{ $att['filename'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

            </div>
        </x-filament::section>

        {{-- Reply form --}}
        <x-filament::section>
            <x-slot name="heading">Reply</x-slot>

            <div
                x-data="{ uploading: false, fileCount: 0 }"
                x-on:livewire-upload-start="uploading = true"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                style="display:flex;flex-direction:column;gap:16px;"
            >
                @error('replyMessage')
                    <p style="font-size:0.875rem;color:var(--danger-600);">{{ $message }}</p>
                @enderror

                <textarea wire:model="replyMessage" rows="5" placeholder="Type your reply here…" class="sp-textarea"></textarea>

                <div style="display:flex;flex-direction:column;gap:4px;">
                    <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">
                        Attachments
                        <span style="font-weight:400;color:var(--gray-400);">(optional, max 10 MB each)</span>
                    </label>
                    @error('replyAttachments.*')
                        <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                    @enderror
                    <input type="file" wire:model="replyAttachments" multiple class="sp-file-input"
                        x-on:change="fileCount = $event.target.files.length">
                    <p x-show="uploading" style="font-size:0.75rem;color:var(--warning-600);">
                        Uploading… please wait before sending.
                    </p>
                    <p x-show="!uploading && fileCount > 0" style="font-size:0.75rem;color:var(--success-600);">
                        <span x-text="fileCount"></span> file(s) ready to send.
                    </p>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <x-filament::button icon="tabler-send" wire:click="submitReply"
                        wire:loading.attr="disabled" x-bind:disabled="uploading">
                        <span x-show="!uploading">Send Reply</span>
                        <span x-show="uploading">Uploading…</span>
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- ── New ticket form ────────────────────────────────────────────── --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @elseif ($this->currentView === 'create')

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <x-filament::button icon="tabler-arrow-left" color="gray" size="sm" wire:click="backToList">
                {{ trans('server/support.back_to_tickets') }}
            </x-filament::button>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ trans('server/support.new_ticket') }}</x-slot>

            <div
                x-data="{ uploading: false, fileCount: 0 }"
                x-on:livewire-upload-start="uploading = true"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                style="display:flex;flex-direction:column;gap:20px;"
            >
                {{-- Subject --}}
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">Subject</label>
                    @error('newSubject')
                        <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                    @enderror
                    <input type="text" wire:model="newSubject"
                        placeholder="Brief description of your issue" class="sp-input">
                </div>

                {{-- Department + Priority --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">Department</label>
                        @error('newDeptId')
                            <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                        @enderror
                        <select wire:model="newDeptId" class="sp-select">
                            <option value="">Select department…</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept['id'] }}">{{ $dept['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">Priority</label>
                        @error('newPriority')
                            <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                        @enderror
                        <select wire:model="newPriority" class="sp-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                </div>

                {{-- Message --}}
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">Message</label>
                    @error('newMessage')
                        <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                    @enderror
                    <textarea wire:model="newMessage" rows="7"
                        placeholder="Describe your issue in detail…" class="sp-textarea"></textarea>
                </div>

                {{-- Attachments --}}
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <label style="font-size:0.875rem;font-weight:500;color:var(--gray-700);">
                        Attachments
                        <span style="font-weight:400;color:var(--gray-400);">(optional, max 10 MB each)</span>
                    </label>
                    @error('newAttachments.*')
                        <p style="font-size:0.75rem;color:var(--danger-600);">{{ $message }}</p>
                    @enderror
                    <input type="file" wire:model="newAttachments" multiple class="sp-file-input"
                        x-on:change="fileCount = $event.target.files.length">
                    <p x-show="uploading" style="font-size:0.75rem;color:var(--warning-600);">
                        Uploading… please wait before sending.
                    </p>
                    <p x-show="!uploading && fileCount > 0" style="font-size:0.75rem;color:var(--success-600);">
                        <span x-text="fileCount"></span> file(s) ready to send.
                    </p>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <x-filament::button icon="tabler-send" wire:click="submitNewTicket"
                        wire:loading.attr="disabled" x-bind:disabled="uploading">
                        <span x-show="!uploading">Submit Ticket</span>
                        <span x-show="uploading">Uploading…</span>
                    </x-filament::button>
                </div>

            </div>
        </x-filament::section>

    @endif

</x-filament-panels::page>
