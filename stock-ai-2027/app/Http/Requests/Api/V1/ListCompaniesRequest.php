<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListCompaniesRequest extends FormRequest
{
    /**
     * Cho phép truy cập danh sách công ty công khai theo cơ chế API hiện tại.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kiểm tra bộ lọc và phân trang của API, giới hạn tối đa 100 công ty mỗi trang.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'com_group_code' => ['nullable', 'string', 'max:255'],
            'icb_code' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
