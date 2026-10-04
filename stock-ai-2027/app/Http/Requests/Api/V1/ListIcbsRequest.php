<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListIcbsRequest extends FormRequest
{
    /**
     * Cho phép đọc danh mục ICB công khai theo cơ chế API hiện tại.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kiểm tra phân trang, giới hạn tối đa 100 ngành mỗi trang.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
