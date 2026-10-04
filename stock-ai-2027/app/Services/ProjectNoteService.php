<?php

namespace App\Services;

use App\Models\ProjectNote;

class ProjectNoteService
{
    /** Đọc ghi chú chung; trả null nếu người dùng chưa lưu nội dung lần nào. */
    public function get(): ?ProjectNote
    {
        return ProjectNote::where('key', 'project')->first();
    }

    /** Lưu vào cùng bản ghi theo key unique, không sinh ghi chú mới mỗi lần bấm lưu. */
    public function save(string $content): ProjectNote
    {
        return ProjectNote::updateOrCreate(['key' => 'project'], ['content' => $content]);
    }
}
