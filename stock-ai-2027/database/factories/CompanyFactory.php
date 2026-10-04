<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tạo dữ liệu công ty phục vụ kiểm thử.
 *
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Tạo ticker duy nhất và thông tin công ty mẫu; test có thể ghi đè theo tình huống.
     *
     * @return array<string, string>
     */
    public function definition(): array
    {
        return [
            'ticker' => fake()->unique()->regexify('[A-Z]{6}'),
            'com_group_code' => 'VNINDEX',
            'icb_code' => '2353',
            'organ_name' => fake()->company(),
            'organ_short_name' => fake()->company(),
        ];
    }
}
