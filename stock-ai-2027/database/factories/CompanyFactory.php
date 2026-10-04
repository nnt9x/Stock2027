<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
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
