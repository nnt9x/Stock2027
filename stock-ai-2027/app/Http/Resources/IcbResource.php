<?php

namespace App\Http\Resources;

use App\Models\Icb;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quy định dữ liệu ngành ICB trả về API.
 *
 * @mixin Icb
 */
class IcbResource extends JsonResource
{
    /**
     * Trả mã ngành, tên tiếng Việt và timestamps; không đưa danh sách công ty vào phản hồi.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Hiển thị tiếng Việt trực tiếp trong JSON thay vì escape Unicode.
     */
    public function jsonOptions(): int
    {
        return JSON_UNESCAPED_UNICODE;
    }
}
