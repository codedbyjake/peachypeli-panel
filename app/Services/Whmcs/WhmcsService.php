<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhmcsService
{
    private string $endpoint;
    private string $identifier;
    private string $secret;

    public function __construct()
    {
        $this->endpoint   = rtrim((string) config('services.whmcs.url'), '/') . '/includes/api.php';
        $this->identifier = (string) config('services.whmcs.identifier');
        $this->secret     = (string) config('services.whmcs.secret');
    }

    public function configured(): bool
    {
        return $this->identifier !== '' && $this->secret !== '' && $this->endpoint !== '/includes/api.php';
    }

    /** @return array<string, mixed> */
    private function call(string $action, array $params = []): array
    {
        $response = Http::asForm()
            ->timeout(10)
            ->post($this->endpoint, array_merge([
                'identifier'   => $this->identifier,
                'secret'       => $this->secret,
                'action'       => $action,
                'responsetype' => 'json',
            ], $params));

        if (!$response->ok()) {
            throw new RuntimeException("WHMCS API HTTP {$response->status()}");
        }

        $data = $response->json() ?? [];

        if (($data['result'] ?? '') === 'error') {
            throw new RuntimeException($data['message'] ?? 'Unknown WHMCS error');
        }

        return $data;
    }

    public function getClientId(string $email): ?int
    {
        $data    = $this->call('GetClients', ['search' => $email, 'limitnum' => 1]);
        $clients = $data['clients']['client'] ?? [];

        if (empty($clients)) {
            return null;
        }

        // WHMCS returns a single item as an assoc array, multiple as a list
        $client = isset($clients[0]) ? $clients[0] : $clients;

        return ($id = (int) ($client['id'] ?? 0)) > 0 ? $id : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTickets(int $clientId): array
    {
        $data    = $this->call('GetTickets', ['clientid' => $clientId, 'limitnum' => 100]);
        $tickets = $data['tickets']['ticket'] ?? [];

        return isset($tickets[0]) ? $tickets : ($tickets ? [$tickets] : []);
    }

    /** @return array<string, mixed> */
    public function getTicket(int $ticketId): array
    {
        return $this->call('GetTicket', ['ticketid' => $ticketId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getDepartments(): array
    {
        $data  = $this->call('GetSupportDepartments');
        $depts = $data['departments']['department'] ?? [];

        return isset($depts[0]) ? $depts : ($depts ? [$depts] : []);
    }

    public function openTicket(
        int $clientId,
        string $name,
        string $email,
        int $deptId,
        string $subject,
        string $message,
        string $priority = 'Medium',
    ): void {
        $this->call('OpenTicket', [
            'clientid' => $clientId,
            'name'     => $name,
            'email'    => $email,
            'deptid'   => $deptId,
            'subject'  => $subject,
            'message'  => $message,
            'priority' => $priority,
        ]);
    }

    public function addReply(int $ticketId, int $clientId, string $message): void
    {
        $this->call('AddTicketReply', [
            'ticketid' => $ticketId,
            'clientid' => $clientId,
            'message'  => $message,
        ]);
    }
}
