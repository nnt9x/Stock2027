<?php

namespace Tests\Feature;

use App\Livewire\Companies\Index;
use App\Models\Company;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CompaniesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_companies_page_renders_inside_the_default_layout(): void
    {
        $this->get('/companies')->assertOk()->assertSee('Đồng bộ từ SSI')->assertSee('StockAI');
    }

    public function test_sync_action_refreshes_the_table_with_ssi_companies(): void
    {
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response([
            'status' => 'Success', 'totalCount' => 1, 'items' => [
                ['ticker' => 'DDB', 'comGroupCode' => 'UpcomIndex', 'icbCode' => '2353',
                    'organName' => 'Doanh nghiệp Đông Dương', 'organShortName' => 'Đông Dương'],
            ],
        ])]);

        Livewire::test(Index::class)->call('sync')->assertSet('errorMessage', null)
            ->assertSee('Đã đồng bộ 1 công ty')->assertSee('Doanh nghiệp Đông Dương');

        $this->assertDatabaseHas('companies', ['ticker' => 'DDB']);
    }

    public function test_filters_combine_exchange_industry_and_ticker_and_hide_deleted_companies(): void
    {
        Company::factory()->create(['ticker' => 'DDB', 'com_group_code' => 'UpcomIndex', 'icb_code' => '2353']);
        Company::factory()->create(['ticker' => 'DDD', 'com_group_code' => 'VNINDEX', 'icb_code' => '2353']);
        Company::factory()->create(['ticker' => 'DDC', 'com_group_code' => 'UpcomIndex', 'icb_code' => '3353']);
        Company::factory()->create(['ticker' => 'DDE', 'com_group_code' => 'UpcomIndex', 'icb_code' => '2353'])->delete();

        Livewire::test(Index::class)->set('search', 'dd')->set('exchange', 'UpcomIndex')->set('industry', '2353')
            ->assertViewHas('companies', fn ($companies): bool => $companies->pluck('ticker')->all() === ['DDB']);
    }

    public function test_http_failure_shows_an_error_and_leaves_the_database_unchanged(): void
    {
        Company::factory()->create(['ticker' => 'DDB']);
        Http::preventStrayRequests();
        Http::fake(['fiin-core.ssi.com.vn/Master/GetListOrganization*' => Http::response([], 403)]);

        Livewire::test(Index::class)->call('sync')->assertSet('successMessage', null)
            ->assertSee('Không thể đồng bộ lúc này')->assertSee('DDB');

        $this->assertDatabaseCount('companies', 1);
    }
}
