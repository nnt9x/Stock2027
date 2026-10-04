<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ticker', 'resolution', 'synced_through_timestamp', 'reload_through_timestamp', 'reload_version', 'completed_reload_version', 'last_success_at', 'last_error', 'data_version'])]
class OhlcvSyncState extends Model
{
    /**
     * Ép kiểu mốc tiến độ và phiên bản yêu cầu; phiên bản mới không bị job cũ xóa mất.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_version' => 'integer',
            'synced_through_timestamp' => 'integer',
            'reload_through_timestamp' => 'integer',
            'reload_version' => 'integer',
            'completed_reload_version' => 'integer',
            'last_success_at' => 'datetime',
        ];
    }
}
