<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Icb;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_deleted_companies_are_excluded_from_ticker_and_industry_searches_and_can_be_restored(): void
    {
        $company = $this->createCompany();

        $company->delete();

        $this->assertSoftDeleted($company);
        $this->assertNull(Company::where('ticker', 'DDB')->first());
        $this->assertCount(0, Company::where('icb_code', '2353')->get());

        $company->restore();

        $this->assertNotSoftDeleted($company);
        $this->assertSame($company->id, Company::where('ticker', 'DDB')->sole()->id);
        $this->assertSame([$company->id], Company::where('icb_code', '2353')->pluck('id')->all());
    }

    public function test_ticker_cannot_be_reused_after_soft_deletion(): void
    {
        $company = $this->createCompany();
        $company->delete();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createCompany();
    }

    private function createCompany(): Company
    {
        return Company::unguarded(fn (): Company => Company::create([
            'ticker' => 'DDB',
            'com_group_code' => 'UpcomIndex',
            'icb_code' => '2353',
            'organ_name' => 'Công ty Cổ Phần Thương Mại Và Xây Dựng Đông Dương',
            'organ_short_name' => 'TM & XD Đông Dương',
        ]));
    }

    public function test_company_fields_can_be_created_and_updated_with_mass_assignment(): void
    {
        $company = Company::create([
            'ticker' => 'ACB', 'com_group_code' => 'VNINDEX', 'icb_code' => '8355',
            'organ_name' => 'Ngân hàng Á Châu', 'organ_short_name' => 'ACB',
        ]);

        $company->update(['organ_name' => 'Ngân hàng Thương mại Cổ phần Á Châu']);

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'ticker' => 'ACB',
            'organ_name' => 'Ngân hàng Thương mại Cổ phần Á Châu']);
    }

    public function test_company_and_industry_relationships_match_by_code(): void
    {
        $icb = Icb::factory()->create(['code' => '0533']);
        $company = Company::factory()->create(['icb_code' => '0533']);
        Company::factory()->create(['icb_code' => 'UNKNOWN']);

        $this->assertSame($icb->id, $company->icb->id);
        $this->assertSame([$company->id], $icb->companies->pluck('id')->all());
    }

    public function test_missing_and_soft_deleted_industries_resolve_to_null(): void
    {
        $icb = Icb::factory()->create(['code' => '0533']);
        $icb->delete();
        $company = Company::factory()->create(['icb_code' => '0533']);
        $unknown = Company::factory()->create(['icb_code' => 'UNKNOWN']);

        $this->assertNull($company->icb);
        $this->assertNull($unknown->icb);
    }
}
