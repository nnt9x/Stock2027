<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Báo lỗi nghiệp vụ hoặc dữ liệu nguồn khi đồng bộ để API và Livewire xử lý riêng.
 */
class CompanySyncException extends RuntimeException {}
