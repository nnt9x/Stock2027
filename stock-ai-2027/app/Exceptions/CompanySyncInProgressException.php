<?php

namespace App\Exceptions;

/**
 * Báo có lần đồng bộ đang chạy; API trả HTTP 409, Livewire hiển thị thông báo thử lại.
 */
class CompanySyncInProgressException extends CompanySyncException {}
