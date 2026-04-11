<?php

namespace Tests\Feature\Distribution;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExportPendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_only_pending_orders_with_complete_coordinates(): void
    {
        config()->set('services.route_optimizer.orders_url', 'http://localhost:8009/api/orders');
        config()->set('services.route_optimizer.time_window_start', '09:00');
        config()->set('services.route_optimizer.time_window_end', '18:00');

        Http::fake([
            'http://localhost:8009/api/orders' => Http::response(['accepted' => true, 'external_id' => 'opt-001'], 201),
        ]);

        $company = Company::create([
            'name' => 'TMS Test',
            'tax_id' => 'TMS-TEST-001',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Cliente Test',
            'is_active' => true,
        ]);

        $exportableOrder = DeliveryOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference_number' => 'PED-EXP-001',
            'delivery_address' => 'Av. Siempre Viva 123',
            'delivery_lat' => -12.0463,
            'delivery_lng' => -77.0427,
            'weight_kg' => 1250.5,
            'requested_date' => '2026-04-10',
            'status' => 'pending',
            'notes' => 'Timbre piso 3',
        ]);

        DeliveryOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference_number' => 'PED-EXP-002',
            'delivery_address' => 'Av. Sin Coordenadas 456',
            'requested_date' => '2026-04-10',
            'status' => 'pending',
            'notes' => 'No se puede exportar',
        ]);

        DeliveryOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference_number' => 'PED-EXP-003',
            'delivery_address' => 'Av. Programada 789',
            'delivery_lat' => -12.0001,
            'delivery_lng' => -77.0001,
            'requested_date' => '2026-04-10',
            'status' => 'scheduled',
        ]);

        DeliveryOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference_number' => 'PED-EXP-004',
            'delivery_address' => 'Av. Ya Exportada 111',
            'delivery_lat' => -12.1001,
            'delivery_lng' => -77.1001,
            'requested_date' => '2026-04-10',
            'status' => 'pending',
            'optimizer_exported_at' => now(),
            'optimizer_external_id' => 'opt-prev',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/distribution/orders/export-pending', [
                'client_id' => $client->id,
                'requested_date' => '2026-04-10',
            ]);

        $response->assertOk()
            ->assertJsonPath('total_pending', 2)
            ->assertJsonPath('sent_count', 1)
            ->assertJsonPath('failed_count', 1)
            ->assertJsonPath('filters.client_id', $client->id)
            ->assertJsonPath('filters.requested_date', '2026-04-10')
            ->assertJsonPath('sent.0.order_id', $exportableOrder->id)
            ->assertJsonPath('sent.0.payload.address', 'Av. Siempre Viva 123')
            ->assertJsonPath('sent.0.payload.lat', -12.0463)
            ->assertJsonPath('sent.0.payload.lng', -77.0427)
            ->assertJsonPath('sent.0.payload.weight', 1250.5)
            ->assertJsonPath('sent.0.payload.time_window_start', '09:00')
            ->assertJsonPath('sent.0.payload.time_window_end', '18:00')
            ->assertJsonPath('sent.0.payload.notes', 'Timbre piso 3')
            ->assertJsonPath('sent.0.response_body.external_id', 'opt-001');

        $exportableOrder->refresh();

        $this->assertSame('sent', $exportableOrder->status);
        $this->assertNotNull($exportableOrder->optimizer_exported_at);
        $this->assertSame('opt-001', $exportableOrder->optimizer_external_id);
        $this->assertSame('Av. Siempre Viva 123', $exportableOrder->optimizer_last_payload['address'] ?? null);
        $this->assertSame('Timbre piso 3', $exportableOrder->optimizer_last_payload['notes'] ?? null);
        $this->assertTrue($exportableOrder->optimizer_last_response['accepted'] ?? false);
        $this->assertSame('opt-001', $exportableOrder->optimizer_last_response['external_id'] ?? null);
        $this->assertNull($exportableOrder->optimizer_last_error);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8009/api/orders'
                && $request['address'] === 'Av. Siempre Viva 123'
                && $request['lat'] === -12.0463
                && $request['lng'] === -77.0427
                && $request['weight'] === 1250.5
                && $request['time_window_start'] === '09:00'
                && $request['time_window_end'] === '18:00'
                && $request['notes'] === 'Timbre piso 3';
        });
    }

    public function test_it_does_not_mark_order_as_exported_when_external_id_is_missing(): void
    {
        config()->set('services.route_optimizer.orders_url', 'http://localhost:8009/api/orders');

        Http::fake([
            'http://localhost:8009/api/orders' => Http::response(['accepted' => true], 201),
        ]);

        $company = Company::create([
            'name' => 'TMS Test 2',
            'tax_id' => 'TMS-TEST-002',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Cliente Test 2',
            'is_active' => true,
        ]);

        $order = DeliveryOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference_number' => 'PED-EXP-005',
            'delivery_address' => 'Av. Sin External Id 123',
            'delivery_lat' => -12.0463,
            'delivery_lng' => -77.0427,
            'weight_kg' => 100,
            'requested_date' => '2026-04-10',
            'status' => 'pending',
            'notes' => 'Debe fallar por external_id',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/distribution/orders/export-pending');

        $response->assertOk()
            ->assertJsonPath('sent_count', 0)
            ->assertJsonPath('failed_count', 1)
            ->assertJsonPath('failed.0.order_id', $order->id)
            ->assertJsonPath('failed.0.reason', 'Servicio externo respondio sin external_id.');

        $order->refresh();

        $this->assertSame('pending', $order->status);
        $this->assertNull($order->optimizer_exported_at);
        $this->assertNull($order->optimizer_external_id);
        $this->assertSame('Debe fallar por external_id', $order->optimizer_last_payload['notes'] ?? null);
        $this->assertTrue($order->optimizer_last_response['accepted'] ?? false);
        $this->assertSame('Servicio externo respondio sin external_id.', $order->optimizer_last_error);
    }

    public function test_it_marks_order_as_exported_when_external_id_is_nested_in_data(): void
    {
        config()->set('services.route_optimizer.orders_url', 'http://localhost:8009/api/orders');

        Http::fake([
            'http://localhost:8009/api/orders' => Http::response([
                'success' => true,
                'data' => [
                    'id' => 109,
                    'address' => 'Av. Respuesta Anidada 123',
                ],
            ], 201),
        ]);

        $company = Company::create([
            'name' => 'TMS Test 3',
            'tax_id' => 'TMS-TEST-003',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Cliente Test 3',
            'is_active' => true,
        ]);

        $order = DeliveryOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference_number' => 'PED-EXP-006',
            'delivery_address' => 'Av. Respuesta Anidada 123',
            'delivery_lat' => -12.0463,
            'delivery_lng' => -77.0427,
            'weight_kg' => 100,
            'requested_date' => '2026-04-10',
            'status' => 'pending',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/distribution/orders/export-pending');

        $response->assertOk()
            ->assertJsonPath('sent_count', 1)
            ->assertJsonPath('failed_count', 0)
            ->assertJsonPath('sent.0.response_body.data.id', 109);

        $order->refresh();

        $this->assertSame('sent', $order->status);
        $this->assertNotNull($order->optimizer_exported_at);
        $this->assertSame('109', $order->optimizer_external_id);
        $this->assertNull($order->optimizer_last_error);
    }
}