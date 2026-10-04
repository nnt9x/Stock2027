<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold tracking-tight">Ghi chú dự án</h1>
        <p class="text-gray-500 dark:text-gray-400">Ghi lại kế hoạch, ý tưởng và các công việc cần làm với StockAI.</p>
    </div>
    <x-card>
        <form wire:submit="save" class="flex flex-col gap-4">
            <x-editor wire:model="content" markdown label="Nội dung ghi chú" placeholder="Viết những việc bạn muốn làm với dự án…"
                :toolbar="['style', 'bold', 'italic', 'strikethrough', 'ordered-list', 'unordered-list', 'blockquote', 'code', 'code-block', 'link', 'undo', 'redo', 'fullscreen']"
                min-height="24rem" max-height="65vh" />
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <span wire:dirty.remove wire:target="content">{{ $savedAt ? 'Đã lưu lúc '.$savedAt.' (giờ Việt Nam)' : 'Chưa có ghi chú được lưu.' }}</span>
                    <span wire:dirty wire:target="content">Có thay đổi chưa lưu.</span>
                </div>
                <x-button type="submit" icon="document-check" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Lưu ghi chú</span>
                    <span wire:loading wire:target="save">Đang lưu…</span>
                </x-button>
            </div>
        </form>
    </x-card>
</div>
