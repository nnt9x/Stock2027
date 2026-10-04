<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Icb;
use Database\Seeders\IcbSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class IcbSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_imports_all_default_industries_and_preserves_leading_zeroes(): void
    {
        $this->seed(IcbSeeder::class);

        $this->assertDatabaseCount('icbs', 116);
        $this->assertDatabaseHas('icbs', ['code' => '0533', 'name' => 'Thăm dò và sản xuất dầu khí']);
        $this->assertDatabaseHas('icbs', ['code' => '2353', 'name' => 'Thiết bị, vật liệu xây dựng']);
    }

    public function test_reseeding_updates_names_without_duplicates_or_replacing_existing_ids(): void
    {
        $icb = Icb::factory()->create(['code' => '0533', 'name' => 'Tên cũ']);
        $custom = Icb::factory()->create(['code' => 'CUSTOM', 'name' => 'Ngành bổ sung']);
        $createdAt = $icb->created_at->toDateTimeString();

        $this->seed(IcbSeeder::class);
        $this->seed(IcbSeeder::class);

        $this->assertDatabaseCount('icbs', 117);
        $this->assertDatabaseHas('icbs', ['id' => $icb->id, 'code' => '0533',
            'name' => 'Thăm dò và sản xuất dầu khí', 'created_at' => $createdAt]);
        $this->assertModelExists($custom);
    }

    public function test_companies_can_use_industry_codes_missing_from_the_icb_catalog(): void
    {
        $company = Company::factory()->create(['icb_code' => 'UNKNOWN']);

        $this->seed(IcbSeeder::class);

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'icb_code' => 'UNKNOWN']);
    }

    public function test_reseeding_preserves_soft_deletion_and_industries_can_be_restored(): void
    {
        $icb = Icb::factory()->create(['code' => '0533', 'name' => 'Tên cũ']);
        $icb->delete();

        $this->seed(IcbSeeder::class);

        $this->assertSoftDeleted($icb);
        $this->assertNull(Icb::where('code', '0533')->first());
        $this->assertDatabaseHas('icbs', ['id' => $icb->id, 'name' => 'Thăm dò và sản xuất dầu khí']);

        $icb->restore();

        $this->assertNotSoftDeleted($icb);
        $this->assertSame($icb->id, Icb::where('code', '0533')->sole()->id);
    }
}
