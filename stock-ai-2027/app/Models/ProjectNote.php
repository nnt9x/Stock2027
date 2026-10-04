<?php

namespace App\Models;

use Database\Factories\ProjectNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Ghi chú công việc chung của dự án; lưu Markdown để giữ định dạng editor. */
#[Fillable(['key', 'content'])]
class ProjectNote extends Model
{
    /**
     * Tạo ghi chú mẫu cho kiểm thử.
     *
     * @use HasFactory<ProjectNoteFactory>
     */
    use HasFactory;
}
