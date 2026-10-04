<?php

namespace Tests\Feature;

use App\Livewire\Home;
use App\Models\ProjectNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectNoteTest extends TestCase
{
    use RefreshDatabase;

    /** Lưu rồi mở lại giữ nội dung Markdown; nhiều lần lưu cập nhật cùng một bản ghi. */
    public function test_save_and_reopen_project_note(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Ghi chú dự án');
        Livewire::test(Home::class)->assertSet('content', '')
            ->set('content', "# Việc cần làm\n\n- Thêm bộ lọc cổ phiếu")
            ->call('save')->assertHasNoErrors();
        Livewire::test(Home::class)->assertSet('content', "# Việc cần làm\n\n- Thêm bộ lọc cổ phiếu")
            ->set('content', '**Đã hoàn tất**')->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('project_notes', 1);
        $this->assertDatabaseHas('project_notes', ['key' => 'project', 'content' => '**Đã hoàn tất**']);
    }

    /** Cho phép xóa nội dung; chặn ghi chú quá lớn và giữ dữ liệu đã lưu khi validation lỗi. */
    public function test_empty_note_and_size_limit(): void
    {
        ProjectNote::factory()->create(['key' => 'project', 'content' => 'Bản cũ']);
        $page = Livewire::test(Home::class)->set('content', str_repeat('a', 100001))
            ->call('save')->assertHasErrors(['content' => 'max']);
        $this->assertDatabaseHas('project_notes', ['content' => 'Bản cũ']);
        $page->set('content', '')->call('save')->assertHasNoErrors();
        Livewire::test(Home::class)->assertSet('content', '');
    }
}
