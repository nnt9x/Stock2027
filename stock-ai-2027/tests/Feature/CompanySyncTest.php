<?php

namespace Tests\Feature;

use App\Exceptions\CompanySyncException;
use App\Models\Company;
use App\Services\CompanySyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanySyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_updates_existing_companies_inserts_new_ones_and_preserves_soft_deletion(): void
    {
        $existing = Company::factory()->create(['ticker' => 'DDB']);
        $deleted = Company::factory()->create(['ticker' => 'FPT']);
        $deleted->delete();
        $absent = Company::factory()->create(['ticker' => 'SSI']);
        $createdAt = $existing->created_at->toDateTimeString();
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response([
            'status' => 'Success', 'totalCount' => 3,
            'items' => [$this->organization('DDB'), $this->organization('FPT'), $this->organization('VVS')],
        ])]);

        $service = app(CompanySyncService::class);
        $this->assertSame(3, $service->sync());
        $this->assertSame(3, $service->sync());

        $this->assertDatabaseCount('companies', 4);
        $this->assertDatabaseHas('companies', ['id' => $existing->id, 'organ_name' => 'Doanh nghiệp DDB', 'created_at' => $createdAt]);
        $this->assertDatabaseHas('companies', ['ticker' => 'VVS', 'icb_code' => '2353']);
        $this->assertSoftDeleted($deleted);
        $this->assertNotSoftDeleted($absent);
        Http::assertSent(fn (Request $request): bool => $request['language'] === 'vi'
            && $request->hasHeader('x-fiin-user-token', 'x') && ! $request->hasHeader('Cookie'));
    }

    public function test_invalid_payload_does_not_write_any_companies_and_releases_the_lock(): void
    {
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response([
            'status' => 'Success', 'totalCount' => 2, 'items' => [$this->organization('DDB')],
        ])]);

        try {
            app(CompanySyncService::class)->sync();
            $this->fail('Expected invalid payload to be rejected.');
        } catch (CompanySyncException $exception) {
            $this->assertDatabaseCount('companies', 0);
        }

        $lock = Cache::lock('companies:sync', 120);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_concurrent_sync_is_rejected_before_calling_ssi(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $lock = Cache::lock('companies:sync', 120);
        $lock->get();

        try {
            app(CompanySyncService::class)->sync();
            $this->fail('Expected concurrent sync to be rejected.');
        } catch (CompanySyncException $exception) {
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    private function organization(string $ticker): array
    {
        return ['ticker' => $ticker, 'comGroupCode' => 'UpcomIndex', 'icbCode' => '2353',
            'organName' => 'Doanh nghiệp '.$ticker, 'organShortName' => $ticker];
    }
}
