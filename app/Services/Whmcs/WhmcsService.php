<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        Log::info('WHMCS API call', [
            'action' => $action,
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

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
        Log::info('WHMCS getTickets called', ['clientId' => $clientId]);

        $data    = $this->call('GetTickets', ['clientid' => $clientId, 'limitnum' => 100]);
        $tickets = $data['tickets']['ticket'] ?? [];

        Log::info('WHMCS getTickets with clientid result', [
            'clientId'     => $clientId,
            'totalresults' => $data['totalresults'] ?? 'n/a',
            'raw_tickets'  => $tickets,
        ]);

        $normalised = isset($tickets[0]) ? $tickets : ($tickets ? [$tickets] : []);

        if (empty($normalised)) {
            $allData    = $this->call('GetTickets', ['limitnum' => 25]);
            $allTickets = $allData['tickets']['ticket'] ?? [];

            Log::info('WHMCS getTickets without clientid (debug fallback)', [
                'totalresults' => $allData['totalresults'] ?? 'n/a',
                'raw_tickets'  => $allTickets,
            ]);
        }

        return $normalised;
    }

    /** @return array<string, mixed> */
    public function getTicket(int $ticketId): array
    {
        $data = $this->call('GetTicket', ['ticketid' => $ticketId]);

        Log::info('WHMCS GetTicket attachments', [
            'ticket_attachments'       => $data['attachments'] ?? 'none',
            'first_reply_attachments'  => $data['replies']['reply'][0]['attachments']
                ?? ($data['replies']['reply']['attachments'] ?? 'none'),
        ]);

        return $data;
    }

    /** @return array<int, array<string, mixed>> */
    public function getClientProducts(int $clientId): array
    {
        $data     = $this->call('GetClientsProducts', ['clientid' => $clientId, 'limitnum' => 100]);
        $products = $data['products']['product'] ?? [];

        $normalised = isset($products[0]) ? $products : ($products ? [$products] : []);

        return array_values(array_filter(
            array_map(fn (array $p): array => [
                'id'     => (int) ($p['id'] ?? 0),
                'name'   => $p['name'] ?? 'Unknown',
                'domain' => $p['domain'] ?? '',
                'status' => $p['status'] ?? '',
            ], $normalised),
            fn (array $p): bool => $p['id'] > 0 && strtolower($p['status']) === 'active',
        ));
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
        ?int $serviceId = null,
    ): void {
        $params = [
            'clientid' => $clientId,
            'name'     => $name,
            'email'    => $email,
            'deptid'   => $deptId,
            'subject'  => $subject,
            'message'  => $message,
            'priority' => $priority,
        ];

        if ($serviceId) {
            $params['serviceid'] = $serviceId;
        }

        $this->call('OpenTicket', $params);
    }

    public function addReply(int $ticketId, int $clientId, string $message): void
    {
        $this->call('AddTicketReply', [
            'ticketid' => $ticketId,
            'clientid' => $clientId,
            'message'  => $message,
        ]);
    }

    public function closeTicket(int $ticketId): void
    {
        $this->call('UpdateTicket', [
            'ticketid' => $ticketId,
            'status'   => 'Closed',
        ]);
    }
}
