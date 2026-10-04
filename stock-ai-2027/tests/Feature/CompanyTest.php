<?php

namespace Tests\Feature;

use App\Models\Company;
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
}
