<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quy định dữ liệu công ty trả về API, độc lập với cách hiển thị của Livewire.
 *
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * Xuất thông tin công ty cùng mã ngành; tên ngành là null nếu LEFT JOIN không tìm thấy.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticker' => $this->ticker,
            'com_group_code' => $this->com_group_code,
            'icb_code' => $this->icb_code,
            'icb_name' => $this->icb_name === null ? null : (string) $this->icb_name,
            'organ_name' => $this->organ_name,
            'organ_short_name' => $this->organ_short_name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Giữ chữ tiếng Việt trực tiếp trong JSON thay vì chuyển thành chuỗi escape Unicode.
     */
    public function jsonOptions(): int
    {
        return JSON_UNESCAPED_UNICODE;
    }
}
