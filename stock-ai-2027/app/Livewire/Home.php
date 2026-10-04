<?php

namespace App\Livewire;

use App\Services\ProjectNoteService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use TallStackUi\Traits\Interactions;
use Throwable;

class Home extends Component
{
    use Interactions;

    #[Validate('present|string|max:100000', message: ['max' => 'Ghi chú tối đa 100.000 ký tự.'])]
    public string $content = '';

    #[Locked]
    public ?string $savedAt = null;

    /** Tải ghi chú từ DB khi mở trang; nội dung mới chưa lưu thì để trống. */
    public function mount(ProjectNoteService $notes): void
    {
        $note = $notes->get();
        $this->content = $note?->content ?? '';
        $this->savedAt = $note?->updated_at?->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s');
    }

    /** Lưu nội dung editor sau validation và thông báo khi DB xác nhận thành công. */
    public function save(ProjectNoteService $notes): void
    {
        $this->validate();
        try {
            $note = $notes->save($this->content);
            $this->savedAt = $note->updated_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s');
            $this->toast()->success('Đã lưu ghi chú dự án.')->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->toast()->error('Không thể lưu ghi chú lúc này. Nội dung vẫn được giữ trong editor.')->send();
        }
    }

    /** Render trang chủ Livewire với editor sẵn có của TallStackUI. */
    public function render(): View
    {
        return view('livewire.home')->layout('components.layouts.app', ['title' => 'Trang chủ']);
    }
}
