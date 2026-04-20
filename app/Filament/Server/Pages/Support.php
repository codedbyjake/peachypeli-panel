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

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $replyAttachments = [];

    // ── New ticket form ───────────────────────────────────────────────────────
    public string $newSubject  = '';
    public string $newDeptId   = '';
    public string $newPriority = 'Medium';
    public string $newMessage  = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newAttachments = [];

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
            );
        } catch (\Exception $e) {
            Log::error('WHMCS Support submitNewTicket error: ' . $e->getMessage());
            Notification::make()->title(trans('server/support.ticket_submit_failed'))->danger()->send();
            return;
        }

        $this->newSubject     = '';
        $this->newDeptId      = '';
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
        $cfg = config('backups.disks.s3');

        Log::info('WHMCS Support uploadToS3 called', [
            'folder'     => $folder,
            'file_count' => count($files),
            's3_bucket'  => $cfg['bucket'] ?? 'NOT SET',
            's3_region'  => $cfg['region'] ?? 'NOT SET',
            's3_endpoint' => $cfg['endpoint'] ?? 'none',
            's3_key_set'  => !empty($cfg['key']),
            's3_secret_set' => !empty($cfg['secret']),
        ]);

        if (empty($cfg['key']) || empty($cfg['secret']) || empty($cfg['bucket'])) {
            Log::warning('WHMCS Support: S3 not configured — missing key, secret, or bucket');
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

        $client = new S3Client($clientConfig);
        $bucket = $cfg['bucket'];
        $urls   = [];

        foreach ($files as $i => $file) {
            if (!$file instanceof UploadedFile) {
                Log::warning("WHMCS Support uploadToS3: item {$i} is not an UploadedFile", [
                    'type' => get_class($file),
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
                    'ACL'         => 'public-read',
                ]);

                $url = $this->buildS3Url($cfg, $bucket, $key);

                Log::info("WHMCS Support uploadToS3: file {$i} uploaded", [
                    'key'          => $key,
                    'etag'         => $result['ETag'] ?? 'n/a',
                    'generated_url' => $url,
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
     * Build the public URL for an S3 object.
     *
     * @param  array<string, mixed>  $cfg
     */
    private function buildS3Url(array $cfg, string $bucket, string $key): string
    {
        if (!empty($cfg['endpoint'])) {
            $base = rtrim($cfg['endpoint'], '/');

            return !empty($cfg['use_path_style_endpoint'])
                ? "{$base}/{$bucket}/{$key}"
                : "{$base}/{$key}";
        }

        $region = $cfg['region'] ?? 'us-east-1';

        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$key}";
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
