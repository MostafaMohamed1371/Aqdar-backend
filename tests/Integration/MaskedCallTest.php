<?php

namespace Tests\Integration;

use App\Core\Enums\CaseStatus;
use App\Core\Enums\MaskedCallStatus;
use App\Modules\Crm\Models\MaskedCall;
use App\Modules\Crm\Models\TransferCase;
use App\Modules\Crm\Services\MaskedCall\MaskedCallGateway;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaskedCallTest extends TestCase
{
    use RefreshDatabase;

    private function callsUrl(TransferCase $case): string
    {
        return '/api/v1/my/cases/'.$case->id.'/calls';
    }

    public function test_buyer_can_request_a_call_and_the_default_driver_reports_it_pending(): void
    {
        $case = TransferCase::factory()->create();
        Sanctum::actingAs($case->buyer);

        $this->postJson($this->callsUrl($case))
            ->assertCreated()
            ->assertJsonPath('data.status', MaskedCallStatus::PendingProvider->value)
            ->assertJsonPath('data.proxy_number', null);

        $this->assertSame(1, MaskedCall::query()->where('case_id', $case->id)->where('caller_user_id', $case->buyer_id)->count());
    }

    public function test_the_connected_number_is_returned_when_a_provider_supplies_one(): void
    {
        $this->app->instance(MaskedCallGateway::class, new class implements MaskedCallGateway
        {
            public function connect(TransferCase $case, User $caller): array
            {
                return ['provider_session_id' => 'sess_1', 'proxy_number' => '+20100000000'];
            }
        });
        $case = TransferCase::factory()->create();
        Sanctum::actingAs($case->seller);

        $this->postJson($this->callsUrl($case))
            ->assertCreated()
            ->assertJsonPath('data.status', MaskedCallStatus::Connected->value)
            ->assertJsonPath('data.proxy_number', '+20100000000');
    }

    public function test_a_provider_failure_is_recorded_without_breaking_the_request(): void
    {
        $this->app->instance(MaskedCallGateway::class, new class implements MaskedCallGateway
        {
            public function connect(TransferCase $case, User $caller): array
            {
                throw new \RuntimeException('provider down');
            }
        });
        $case = TransferCase::factory()->create();
        Sanctum::actingAs($case->buyer);

        $this->postJson($this->callsUrl($case))
            ->assertCreated()
            ->assertJsonPath('data.status', MaskedCallStatus::Failed->value);
    }

    public function test_a_stranger_cannot_start_a_call_on_someone_elses_case(): void
    {
        $case = TransferCase::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson($this->callsUrl($case))->assertNotFound();
    }

    public function test_calls_are_not_available_on_a_closed_case(): void
    {
        $case = TransferCase::factory()->create(['status' => CaseStatus::Completed]);
        Sanctum::actingAs($case->buyer);

        $this->postJson($this->callsUrl($case))->assertUnprocessable();
    }

    public function test_a_caller_is_limited_to_the_configured_calls_per_hour(): void
    {
        config(['aqdar.telephony.calls_per_hour' => 2]);
        $case = TransferCase::factory()->create();
        Sanctum::actingAs($case->buyer);

        $this->postJson($this->callsUrl($case))->assertCreated();
        $this->postJson($this->callsUrl($case))->assertCreated();
        $this->postJson($this->callsUrl($case))->assertStatus(429);

        $this->assertSame(2, MaskedCall::query()->where('case_id', $case->id)->count());
    }
}
