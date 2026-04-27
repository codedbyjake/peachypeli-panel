<?php

namespace App\Filament\Server\Pages;

use App\Enums\TablerIcon;
use App\Models\Server;
use App\Services\Whmcs\WhmcsService;
use Aws\S3\S3Client;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

class Support extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Headset;

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.server.pages.support';

    public ?Server $server = null;

    // ── View state ───────────────────────────────────────────────────────────
    public string $currentView = 'list'; // 'list' | 'ticket' | 'create'

    public string $ticketTab = 'open'; // 'open' | 'closed'

    // ── Data ─────────────────────────────────────────────────────────────────
    /** @var array<int, array<string, mixed>> */
    public array $tickets = [];

    /** @var array<string, mixed> */
    public array $ticket = [];

    /** @var array<int, array<string, mixed>> */
    public array $departments = [];

    /** @var array<int, array<string, mixed>> */
    public array $services = [];

    public ?int $whmcsClientId = null;

    // ── UI state ─────────────────────────────────────────────────────────────
    public bool $loading = false;

    public ?string $error = null;

    // ── Reply form ────────────────────────────────────────────────────────────
    public string $replyMessage = '';

    // No array type hint — Livewire needs to hydrate TemporaryUploadedFile instances
    public $replyAttachments = [];

    // ── New ticket form ───────────────────────────────────────────────────────
    public string $newSubject    = '';
    public string $newDeptId    = '';
    public string $newServiceId = '';
    public string $newPriority  = 'Medium';
    public string $newMessage   = '';

    // No array type hint — Livewire needs to hydrate TemporaryUploadedFile instances
    public $newAttachments = [];

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
            $this->services    = $this->whmcs->getClientProducts($this->whmcsClientId);
        } catch (\Exception $e) {
            Log::error('WHMCS Support initialise error: ' . $e->getMessage());
            $this->error = trans('server/support.unavailable');
        } finally {
            $this->loading = false;
        }
    }

    public function refreshTickets(): void
    {
        $this->initialise();
    }

    public function setTicketTab(string $tab): void
    {
        $this->ticketTab = $tab;
    }

    public function viewTicket(int $ticketId): void
    {
        $this->loading = true;
        $this->error   = null;

        try {
            $this->ticket           = $this->whmcs->getTicket($ticketId);
            $this->currentView      = 'ticket';
            $this->replyMessage     = '';
            $this->replyAttachments = [];
        } catch (\Exception $e) {
            Log::error('WHMCS Support viewTicket error: ' . $e->getMessage());
            $this->error = trans('server/support.unavailable');
        } finally {
            $this->loading = false;
        }
    }

    public function submitReply(): void
    {
        $this->validate([
            'replyMessage'       => 'required|min:5',
            'replyAttachments'   => 'nullable|array',
            'replyAttachments.*' => 'file|max:10240',
        ]);

        Log::info('WHMCS Support submitReply: starting', [
            'ticket_id'       => $this->ticket['ticketid'] ?? 'unknown',
            'attachment_count' => count($this->replyAttachments),
            'attachment_types' => array_map(fn($f) => get_class($f), $this->replyAttachments),
        ]);

        try {
            $ticketId = (int) $this->ticket['ticketid'];
            $message  = $this->appendS3Links($this->replyMessage, $this->replyAttachments, "support-attachments/{$ticketId}");

            $this->whmcs->addReply($ticketId, $this->whmcsClientId, $message);

            $this->replyMessage     = '';
            $this->replyAttachments = [];
            $this->ticket           = $this->whmcs->getTicket($ticketId);

            try {
                $this->tickets = $this->whmcs->getTickets($this->whmcsClientId);
            } catch (\Exception) {}

            Notification::make()->title(trans('server/support.reply_submitted'))->success()->send();
        } catch (\Exception $e) {
            Log::error('WHMCS Support submitReply error: ' . $e->getMessage());
            Notification::make()->title(trans('server/support.reply_submit_failed'))->danger()->send();
        }
    }

    public function submitNewTicket(): void
    {
        $this->validate([
            'newSubject'       => 'required|min:5|max:255',
            'newDeptId'        => 'required',
            'newServiceId'     => 'required',
            'newPriority'      => 'required|in:Low,Medium,High',
            'newMessage'       => 'required|min:20',
            'newAttachments'   => 'nullable|array',
            'newAttachments.*' => 'file|max:10240',
        ]);

        Log::info('WHMCS Support submitNewTicket: starting', [
            'attachment_count' => count($this->newAttachments),
            'attachment_types' => array_map(fn($f) => get_class($f), $this->newAttachments),
        ]);

        try {
            $user    = user();
            // Use a UUID folder since we don't have a ticket ID yet
            $folder  = 'support-attachments/' . Str::uuid();
            $message = $this->appendS3Links($this->newMessage, $this->newAttachments, $folder);

            $this->whmcs->openTicket(
                $this->whmcsClientId,
                $user?->username ?? $user?->email ?? 'User',
                $user?->email ?? '',
                (int) $this->newDeptId,
                $this->newSubject,
                $message,
                $this->newPriority,
                $this->newServiceId !== '' ? (int) $this->newServiceId : null,
            );
        } catch (\Exception $e) {
            Log::error('WHMCS Support submitNewTicket error: ' . $e->getMessage());
            Notification::make()->title(trans('server/support.ticket_submit_failed'))->danger()->send();
            return;
        }

        $this->newSubject     = '';
        $this->newDeptId      = '';
        $this->newServiceId   = '';
        $this->newMessage     = '';
        $this->newPriority    = 'Medium';
        $this->newAttachments = [];
        $this->currentView    = 'list';

        Notification::make()->title(trans('server/support.ticket_submitted'))->success()->send();

        try {
            $this->tickets = $this->whmcs->getTickets($this->whmcsClientId);
        } catch (\Exception $e) {
            Log::error('WHMCS Support ticket list refresh error: ' . $e->getMessage());
        }
    }

    /**
     * Upload each file to S3, then return the original message with S3 URLs appended.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function appendS3Links(string $message, array $files, string $folder): string
    {
        Log::info('WHMCS Support appendS3Links called', [
            'folder'     => $folder,
            'file_count' => count($files),
            'file_types' => array_map(fn($f) => get_class($f), $files),
        ]);

        if (empty($files)) {
            Log::info('WHMCS Support appendS3Links: no files, returning original message');
            return $message;
        }

        $urls = $this->uploadToS3($files, $folder);

        Log::info('WHMCS Support appendS3Links: upload complete', [
            'urls_generated' => $urls,
        ]);

        if (empty($urls)) {
            Log::warning('WHMCS Support appendS3Links: no URLs returned, message unchanged');
            return $message;
        }

        $links       = implode("\n", array_map(fn($url) => "📎 {$url}", $urls));
        $finalMessage = $message . "\n\n--- Attachments ---\n" . $links;

        Log::info('WHMCS Support appendS3Links: final message body', [
            'final_message' => $finalMessage,
        ]);

        return $finalMessage;
    }

    /**
     * Upload files to S3 using the existing backup S3 credentials.
     * Returns an array of public URLs.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    private function uploadToS3(array $files, string $folder): array
    {
        // Log full config structure so we can see what keys are actually available
        Log::info('WHMCS Support uploadToS3: raw backups config', [
            'backups_default'      => config('backups.default'),
            'backups_disks_keys'   => array_keys((array) config('backups.disks')),
            'backups_disks_s3_keys'=> array_keys((array) config('backups.disks.s3')),
        ]);

        $cfg = config('backups.disks.s3');

        Log::info('WHMCS Support uploadToS3 called', [
            'folder'            => $folder,
            'file_count'        => count($files),
            's3_bucket'         => $cfg['bucket'] ?? 'NOT SET',
            's3_region'         => $cfg['region'] ?? 'NOT SET',
            's3_endpoint'       => $cfg['endpoint'] ?? 'none',
            's3_path_style'     => $cfg['use_path_style_endpoint'] ?? false,
            's3_key_set'        => !empty($cfg['key']),
            's3_secret_set'     => !empty($cfg['secret']),
            's3_key_length'     => strlen((string) ($cfg['key'] ?? '')),
            's3_secret_length'  => strlen((string) ($cfg['secret'] ?? '')),
        ]);

        if (empty($cfg['key']) || empty($cfg['secret']) || empty($cfg['bucket'])) {
            Log::warning('WHMCS Support: S3 not configured — missing key, secret, or bucket', [
                'has_key'    => !empty($cfg['key']),
                'has_secret' => !empty($cfg['secret']),
                'has_bucket' => !empty($cfg['bucket']),
            ]);
            return [];
        }

        $clientConfig = [
            'version'     => 'latest',
            'region'      => $cfg['region'] ?? 'us-east-1',
            'credentials' => [
                'key'    => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ];

        if (!empty($cfg['endpoint'])) {
            $clientConfig['endpoint'] = $cfg['endpoint'];
        }

        if (!empty($cfg['use_path_style_endpoint'])) {
            $clientConfig['use_path_style_endpoint'] = (bool) $cfg['use_path_style_endpoint'];
        }

        try {
            $client = new S3Client($clientConfig);
            Log::info('WHMCS Support uploadToS3: S3Client constructed OK');
        } catch (\Exception $e) {
            Log::error('WHMCS Support uploadToS3: S3Client construction FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }

        $bucket = $cfg['bucket'];
        $urls   = [];

        Log::info('WHMCS Support uploadToS3: entering loop', [
            'file_count'  => count($files),
            'files_array' => array_map(fn($f) => [
                'class'    => is_object($f) ? get_class($f) : gettype($f),
                'is_uploaded_file' => $f instanceof UploadedFile,
            ], $files),
        ]);

        foreach ($files as $i => $file) {
            Log::info("WHMCS Support uploadToS3: checking file {$i}", [
                'class'            => is_object($file) ? get_class($file) : gettype($file),
                'is_uploaded_file' => $file instanceof UploadedFile,
            ]);

            if (!$file instanceof UploadedFile) {
                Log::warning("WHMCS Support uploadToS3: item {$i} is not an UploadedFile — skipping", [
                    'class' => is_object($file) ? get_class($file) : gettype($file),
                ]);
                continue;
            }

            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                . '.' . $file->getClientOriginalExtension();
            $key = trim($folder, '/') . '/' . $filename;

            Log::info("WHMCS Support uploadToS3: uploading file {$i}", [
                'original_name' => $file->getClientOriginalName(),
                'slugged_name'  => $filename,
                'key'           => $key,
                'size'          => $file->getSize(),
                'mime'          => $file->getMimeType(),
                'real_path'     => $file->getRealPath(),
                'real_path_exists' => file_exists($file->getRealPath() ?? ''),
            ]);

            try {
                $result = $client->putObject([
                    'Bucket'      => $bucket,
                    'Key'         => $key,
                    'Body'        => fopen($file->getRealPath(), 'r'),
                    'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
                ]);

                // Backblaze B2 does not support canned ACLs — use a pre-signed URL instead
                $presigned = $client->createPresignedRequest(
                    $client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]),
                    '+7 days'
                );
                $url = (string) $presigned->getUri();

                Log::info("WHMCS Support uploadToS3: file {$i} uploaded", [
                    'key'           => $key,
                    'etag'          => $result['ETag'] ?? 'n/a',
                    'presigned_url' => $url,
                ]);

                $urls[] = $url;
            } catch (\Exception $e) {
                Log::error("WHMCS Support uploadToS3: file {$i} FAILED", [
                    'key'   => $key,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $urls;
    }

    /**
     * Escape a message body and linkify any URLs for safe HTML rendering.
     */
    public function linkify(string $text): string
    {
        $escaped = nl2br(e(strip_tags($text)));

        return preg_replace_callback(
            '/(https?:\/\/[^\s<>"&]+(?:&amp;[^\s<>"]+)*)/i',
            function (array $m): string {
                $href    = htmlspecialchars_decode($m[1]);
                $display = $m[1];
                return '<a href="' . e($href) . '" target="_blank" rel="noopener" '
                    . 'style="color:var(--primary-600);text-decoration:underline;">'
                    . $display . '</a>';
            },
            $escaped,
        ) ?? $escaped;
    }

    public function closeTicket(): void
    {
        $ticketId = (int) ($this->ticket['ticketid'] ?? 0);

        if (!$ticketId) {
            return;
        }

        try {
            $this->whmcs->closeTicket($ticketId);
            $this->ticket = $this->whmcs->getTicket($ticketId);

            try {
                $this->tickets = $this->whmcs->getTickets($this->whmcsClientId);
            } catch (\Exception) {}

            Notification::make()->title('Ticket closed.')->success()->send();
        } catch (\Exception $e) {
            Log::error('WHMCS Support closeTicket error: ' . $e->getMessage());
            Notification::make()->title('Failed to close ticket. Please try again.')->danger()->send();
        }
    }

    public function backToList(): void
    {
        $this->currentView      = 'list';
        $this->ticket           = [];
        $this->replyMessage     = '';
        $this->replyAttachments = [];
        $this->error            = null;
    }

    public function showCreate(): void
    {
        $this->currentView    = 'create';
        $this->newSubject     = '';
        $this->newDeptId      = '';
        $this->newServiceId   = '';
        $this->newMessage     = '';
        $this->newPriority    = 'Medium';
        $this->newAttachments = [];
        $this->error          = null;
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
