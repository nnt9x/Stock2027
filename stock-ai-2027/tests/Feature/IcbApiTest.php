<?php

namespace Tests\Feature;

use App\Models\Icb;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IcbApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_returns_paginated_industries_sorted_by_code_and_excludes_deleted_records(): void
    {
        Icb::factory()->create(['code' => '8355', 'name' => 'Ngân hàng']);
        Icb::factory()->create(['code' => '0533', 'name' => 'Thăm dò và sản xuất dầu khí']);
        Icb::factory()->create(['code' => '0001'])->delete();

        $this->getJson('/api/v1/icbs?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', '0533')
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/v1/icbs?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.name', 'Ngân hàng')->assertSee('Ngân hàng', false);
    }

    public function test_show_returns_the_exact_industry_code_with_leading_zeroes(): void
    {
        Icb::factory()->create(['code' => '0533', 'name' => 'Thăm dò và sản xuất dầu khí']);

        $this->getJson('/api/v1/icbs/0533')->assertOk()
            ->assertJsonPath('data.code', '0533')->assertSee('Thăm dò và sản xuất dầu khí', false);
        $this->getJson('/api/v1/icbs/533')->assertNotFound();
    }

    public function test_missing_and_deleted_industries_return_404(): void
    {
        Icb::factory()->create(['code' => '8355'])->delete();

        $this->getJson('/api/v1/icbs/8355')->assertNotFound();
        $this->getJson('/api/v1/icbs/UNKNOWN')->assertNotFound();
    }

    #[DataProvider('invalidPagination')]
    public function test_invalid_pagination_returns_422(string $query, string $field): void
    {
        $this->getJson('/api/v1/icbs?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidPagination(): array
    {
        return [
            'zero quantity' => ['per_page=0', 'per_page'],
            'too many items' => ['per_page=101', 'per_page'],
            'non integer quantity' => ['per_page=abc', 'per_page'],
            'zero page' => ['page=0', 'page'],
        ];
    }
}
