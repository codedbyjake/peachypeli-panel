<?php

namespace App\Filament\Server\Pages;

use App\Enums\TablerIcon;
use App\Models\Server;
use App\Services\Whmcs\WhmcsService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Support extends Page
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Headset;

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.server.pages.support';

    public ?Server $server = null;

    // ── View state ───────────────────────────────────────────────────────────
    public string $currentView = 'list'; // 'list' | 'ticket' | 'create'

    // ── Data ─────────────────────────────────────────────────────────────────
    /** @var array<int, array<string, mixed>> */
    public array $tickets = [];

    /** @var array<string, mixed> */
    public array $ticket = [];

    /** @var array<int, array<string, mixed>> */
    public array $departments = [];

    public ?int $whmcsClientId = null;

    // ── UI state ─────────────────────────────────────────────────────────────
    public bool $loading = false;

    public ?string $error = null;

    // ── Reply form ────────────────────────────────────────────────────────────
    public string $replyMessage = '';

    // ── New ticket form ───────────────────────────────────────────────────────
    public string $newSubject  = '';
    public string $newDeptId   = '';
    public string $newPriority = 'Medium';
    public string $newMessage  = '';

    private WhmcsService $whmcs;

    public function boot(WhmcsService $whmcs): void
    {
        $this->whmcs = $whmcs;
    }

    public function mount(): void
    {
        $this->initialise();
    }

    private function initialise(): void
    {
        if (!$this->whmcs->configured()) {
            $this->error = trans('server/support.not_configured');
            return;
        }

        $this->loading = true;
        $this->error   = null;

        try {
            $email = user()?->email ?? '';

            $this->whmcsClientId = $this->whmcs->getClientId($email);

            if (!$this->whmcsClientId) {
                $this->error = trans('server/support.no_account');
                return;
            }

            $this->tickets     = $this->whmcs->getTickets($this->whmcsClientId);
            $this->departments = $this->whmcs->getDepartments();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function refreshTickets(): void
    {
        $this->initialise();
    }

    public function viewTicket(int $ticketId): void
    {
        $this->loading = true;
        $this->error   = null;

        try {
            $this->ticket      = $this->whmcs->getTicket($ticketId);
            $this->currentView = 'ticket';
            $this->replyMessage = '';
        } catch (\Exception) {
            $this->error = trans('server/support.unavailable');
        } finally {
            $this->loading = false;
        }
    }

    public function submitReply(): void
    {
        $this->validate(['replyMessage' => 'required|min:5']);

        try {
            $this->whmcs->addReply((int) $this->ticket['ticketid'], $this->whmcsClientId, $this->replyMessage);

            $this->replyMessage = '';
            $this->ticket = $this->whmcs->getTicket((int) $this->ticket['ticketid']);

            Notification::make()->title(trans('server/support.reply_submitted'))->success()->send();
        } catch (\Exception) {
            Notification::make()->title(trans('server/support.reply_submit_failed'))->danger()->send();
        }
    }

    public function submitNewTicket(): void
    {
        $this->validate([
            'newSubject'  => 'required|min:5|max:255',
            'newDeptId'   => 'required',
            'newPriority' => 'required|in:Low,Medium,High',
            'newMessage'  => 'required|min:20',
        ]);

        try {
            $user = user();
            $this->whmcs->openTicket(
                $this->whmcsClientId,
                $user?->username ?? $user?->email ?? 'User',
                $user?->email ?? '',
                (int) $this->newDeptId,
                $this->newSubject,
                $this->newMessage,
                $this->newPriority,
            );

            $this->newSubject  = '';
            $this->newDeptId   = '';
            $this->newMessage  = '';
            $this->newPriority = 'Medium';

            $this->tickets     = $this->whmcs->getTickets($this->whmcsClientId);
            $this->currentView = 'list';

            Notification::make()->title(trans('server/support.ticket_submitted'))->success()->send();
        } catch (\Exception) {
            Notification::make()->title(trans('server/support.ticket_submit_failed'))->danger()->send();
        }
    }

    public function backToList(): void
    {
        $this->currentView  = 'list';
        $this->ticket       = [];
        $this->replyMessage = '';
        $this->error        = null;
    }

    public function showCreate(): void
    {
        $this->currentView  = 'create';
        $this->newSubject   = '';
        $this->newDeptId    = '';
        $this->newMessage   = '';
        $this->newPriority  = 'Medium';
        $this->error        = null;
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return trans('server/support.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/support.title');
    }
}
