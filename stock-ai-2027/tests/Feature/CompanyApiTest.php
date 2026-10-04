<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_combines_filters_and_returns_paginated_company_resources(): void
    {
        $company = Company::factory()->create(['ticker' => 'DDB', 'com_group_code' => 'UpcomIndex', 'icb_code' => '2353']);
        Company::factory()->create(['ticker' => 'DDD', 'com_group_code' => 'VNINDEX']);
        Company::factory()->create(['ticker' => 'DDE', 'com_group_code' => 'UpcomIndex', 'icb_code' => '3353']);
        Company::factory()->create(['ticker' => 'DDF', 'com_group_code' => 'UpcomIndex', 'icb_code' => '2353'])->delete();

        $this->getJson('/api/v1/companies?search=dd&com_group_code=UpcomIndex&icb_code=2353&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $company->id)
            ->assertJsonPath('data.0.ticker', 'DDB')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1)->assertJsonStructure(['data' => [['organ_name', 'organ_short_name']], 'links', 'meta']);
    }

    public function test_show_finds_a_company_by_case_insensitive_ticker(): void
    {
        Company::factory()->create(['ticker' => 'FPT']);

        $this->getJson('/api/v1/companies/fpt')->assertOk()->assertJsonPath('data.ticker', 'FPT');
    }

    public function test_missing_and_soft_deleted_companies_return_404(): void
    {
        Company::factory()->create(['ticker' => 'DDB'])->delete();

        $this->getJson('/api/v1/companies/DDB')->assertNotFound();
        $this->getJson('/api/v1/companies/MISSING')->assertNotFound();
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_422(string $query, string $field): void
    {
        $this->getJson('/api/v1/companies?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'too many per page' => ['per_page=101', 'per_page'],
            'zero per page' => ['per_page=0', 'per_page'],
            'invalid page' => ['page=0', 'page'],
            'array search' => ['search[]=FPT', 'search'],
            'array exchange' => ['com_group_code[]=VNINDEX', 'com_group_code'],
            'array industry' => ['icb_code[]=2353', 'icb_code'],
        ];
    }

    public function test_sync_endpoint_uses_the_shared_service_and_persists_companies(): void
    {
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response([
            'status' => 'Success', 'totalCount' => 1, 'items' => [
                ['ticker' => 'DDB', 'comGroupCode' => 'UpcomIndex', 'icbCode' => '2353',
                    'organName' => 'Doanh nghiệp Đông Dương', 'organShortName' => 'Đông Dương'],
            ],
        ])]);

        $this->postJson('/api/v1/companies/sync')->assertOk()->assertJsonPath('data.synced', 1);

        $this->assertDatabaseHas('companies', ['ticker' => 'DDB', 'icb_code' => '2353']);
    }

    public function test_sync_returns_409_if_another_sync_is_running(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $lock = Cache::lock('companies:sync', 120);
        $lock->get();

        try {
            $this->postJson('/api/v1/companies/sync')->assertConflict();
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_sync_returns_502_for_upstream_http_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response([], 403)]);

        $this->postJson('/api/v1/companies/sync')->assertStatus(502)
            ->assertJsonPath('message', 'Không thể kết nối hoặc tải dữ liệu từ SSI. Vui lòng thử lại sau.');

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_sync_returns_502_for_invalid_upstream_data(): void
    {
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response(['status' => 'Failure'])]);

        $this->postJson('/api/v1/companies/sync')->assertStatus(502);

        $this->assertDatabaseCount('companies', 0);
    }
}
