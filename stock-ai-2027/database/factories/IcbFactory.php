<?php

namespace Database\Factories;

use App\Models\Icb;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Icb>
 */
class IcbFactory extends Factory
{
    /**
     * Tạo mã ngành dạng chuỗi để giữ số 0 đầu và tên mẫu phục vụ kiểm thử.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
        ];
    }
}
