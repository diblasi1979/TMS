<?php

namespace App\Services\Distribution;

use App\Models\DeliveryOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class PendingOrderExporter
{
    public function export(Collection $orders): array
    {
        $endpoint = trim((string) config('services.route_optimizer.orders_url', ''));
        $results = [
            'endpoint' => $endpoint,
            'total_pending' => $orders->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'sent' => [],
            'failed' => [],
        ];

        if ($orders->isEmpty()) {
            return $results;
        }

        if ($endpoint === '') {
            return $this->failAllOrders(
                $orders,
                $results,
                'ROUTE_OPTIMIZER_ORDERS_URL no esta configurado. Define la URL real del servicio externo.'
            );
        }

        if ($this->isDockerLocalhostEndpoint($endpoint)) {
            return $this->failAllOrders(
                $orders,
                $results,
                'ROUTE_OPTIMIZER_ORDERS_URL apunta a localhost desde Docker. Usa el host real del servicio externo o host.docker.internal si corre en tu maquina.'
            );
        }

        foreach ($orders as $order) {
            if ($order->delivery_lat === null || $order->delivery_lng === null) {
                $this->markAsFailed($order, 'El pedido no tiene coordenadas de entrega completas.');
                $results['failed_count']++;
                $results['failed'][] = [
                    'order_id' => $order->id,
                    'reference_number' => $order->reference_number,
                    'reason' => 'El pedido no tiene coordenadas de entrega completas.',
                ];
                continue;
            }

            $payload = [
                'address' => $order->delivery_address,
                'lat' => $order->delivery_lat,
                'lng' => $order->delivery_lng,
                'weight' => (float) ($order->weight_kg ?? 0),
                'time_window_start' => config('services.route_optimizer.time_window_start', '09:00'),
                'time_window_end' => config('services.route_optimizer.time_window_end', '18:00'),
                'notes' => $order->notes ?: ($order->description ?: ''),
            ];

            try {
                $response = Http::acceptJson()
                    ->timeout(15)
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $responseBody = $response->json();
                    if ($responseBody === null) {
                        $responseBody = ['raw' => $response->body()];
                    }

                    $externalId = $this->extractExternalId($responseBody);

                    if ($externalId === null) {
                        $this->markAsFailed(
                            $order,
                            'Servicio externo respondio sin external_id.',
                            $payload,
                            $responseBody
                        );

                        $results['failed_count']++;
                        $results['failed'][] = [
                            'order_id' => $order->id,
                            'reference_number' => $order->reference_number,
                            'payload' => $payload,
                            'response_status' => $response->status(),
                            'response_body' => $responseBody,
                            'reason' => 'Servicio externo respondio sin external_id.',
                        ];
                        continue;
                    }

                    $this->markAsExported($order, $payload, $responseBody, $externalId);

                    $results['sent_count']++;
                    $results['sent'][] = [
                        'order_id' => $order->id,
                        'reference_number' => $order->reference_number,
                        'payload' => $payload,
                        'response_status' => $response->status(),
                        'response_body' => $responseBody,
                    ];
                    continue;
                }

                $responseBody = $response->json();
                if ($responseBody === null) {
                    $responseBody = ['raw' => $response->body()];
                }

                $this->markAsFailed(
                    $order,
                    sprintf('Servicio externo respondio con estado %d.', $response->status()),
                    $payload,
                    $responseBody
                );

                $results['failed_count']++;
                $results['failed'][] = [
                    'order_id' => $order->id,
                    'reference_number' => $order->reference_number,
                    'payload' => $payload,
                    'response_status' => $response->status(),
                    'response_body' => $responseBody,
                ];
            } catch (Throwable $exception) {
                $this->markAsFailed($order, $exception->getMessage(), $payload);

                $results['failed_count']++;
                $results['failed'][] = [
                    'order_id' => $order->id,
                    'reference_number' => $order->reference_number,
                    'payload' => $payload,
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    protected function markAsExported(DeliveryOrder $order, array $payload, array $responseBody, string $externalId): void
    {
        $order->forceFill([
            'status' => 'sent',
            'optimizer_exported_at' => now(),
            'optimizer_external_id' => $externalId,
            'optimizer_last_payload' => $payload,
            'optimizer_last_response' => $responseBody,
            'optimizer_last_error' => null,
        ])->save();
    }

    protected function markAsFailed(
        DeliveryOrder $order,
        string $reason,
        ?array $payload = null,
        ?array $responseBody = null
    ): void
    {
        $order->forceFill([
            'optimizer_exported_at' => null,
            'optimizer_external_id' => null,
            'optimizer_last_payload' => $payload,
            'optimizer_last_response' => $responseBody,
            'optimizer_last_error' => $reason,
        ])->save();
    }

    protected function extractExternalId(array $responseBody): ?string
    {
        $candidates = [
            $responseBody,
            is_array($responseBody['data'] ?? null) ? $responseBody['data'] : null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            foreach (['external_id', 'id', 'order_id'] as $key) {
                if (isset($candidate[$key]) && $candidate[$key] !== null && $candidate[$key] !== '') {
                    return (string) $candidate[$key];
                }
            }
        }

        return null;
    }

    protected function failAllOrders(Collection $orders, array $results, string $reason): array
    {
        foreach ($orders as $order) {
            $this->markAsFailed($order, $reason);

            $results['failed_count']++;
            $results['failed'][] = [
                'order_id' => $order->id,
                'reference_number' => $order->reference_number,
                'reason' => $reason,
            ];
        }

        return $results;
    }

    protected function isDockerLocalhostEndpoint(string $endpoint): bool
    {
        if (!is_file('/.dockerenv')) {
            return false;
        }

        $host = parse_url($endpoint, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true);
    }
}