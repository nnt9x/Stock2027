<?php

namespace App\Livewire\Companies;

use App\Exceptions\CompanySyncException;
use App\Models\Company;
use App\Models\Icb;
use App\Services\CompanyQueryService;
use App\Services\CompanySyncService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;
use Throwable;

class Index extends Component
{
    use Interactions;
    use WithPagination;

    public string $search = '';

    public string $exchange = '';

    public string $industry = '';

    protected CompanyQueryService $companies;

    /**
     * Nhận service truy vấn ở mỗi request Livewire, không lưu service vào state phía trình duyệt.
     */
    public function boot(CompanyQueryService $companies): void
    {
        $this->companies = $companies;
    }

    /**
     * Quay về trang đầu khi thay đổi ticker, sàn hoặc ngành để tránh trang kết quả trống.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'exchange', 'industry'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Chạy service đồng bộ trực tiếp, sau đó tải lại bảng và hiển thị kết quả cho người dùng.
     * Báo lỗi nghiệp vụ rõ ràng; ghi log lỗi bất ngờ và hiển thị thông báo chung.
     */
    public function sync(CompanySyncService $service): void
    {
        try {
            $count = $service->sync();
            $this->resetPage();
            $this->toast()->success('Đã đồng bộ '.number_format($count).' công ty từ SSI.')->send();
        } catch (CompanySyncException $exception) {
            $this->toast()->error($exception->getMessage())->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->toast()->error('Không thể đồng bộ lúc này. Vui lòng thử lại sau.')->send();
        }
    }

    /**
     * Lấy dữ liệu phân trang và các lựa chọn lọc cho giao diện Company.
     * Hiển thị tên ngành từ danh mục ICB; dùng code nếu ngành chưa có hoặc đã xoá mềm.
     */
    public function render(): View
    {
        $query = $this->companies->query($this->search, $this->exchange, $this->industry);
        $industryNames = Icb::pluck('name', 'code');
        $companies = $query->paginate(25);
        $companies->through(function (Company $company) use ($industryNames): Company {
            $company->setAttribute('industry_name', $industryNames->get($company->icb_code, $company->icb_code));

            return $company;
        });

        return view('livewire.companies.index', [
            'companies' => $companies,
            'totalCompanies' => Company::count(),
            'exchanges' => Company::select('com_group_code')->distinct()->orderBy('com_group_code')->pluck('com_group_code'),
            'industries' => Company::select('icb_code')->distinct()->orderBy('icb_code')->pluck('icb_code'),
            'industryNames' => $industryNames,
        ])->layout('components.layouts.app', ['title' => 'Công ty']);
    }
}
