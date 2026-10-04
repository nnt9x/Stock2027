<?php

namespace Database\Factories;

use App\Models\ProjectNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Dữ liệu ghi chú mẫu để kiểm tra lưu và tải lại nội dung.
 *
 * @extends Factory<ProjectNote>
 */
class ProjectNoteFactory extends Factory
{
    /**
     * Tạo khóa riêng để các ghi chú mẫu không xung đột unique.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['key' => fake()->unique()->uuid(), 'content' => fake()->paragraph()];
    }
}
