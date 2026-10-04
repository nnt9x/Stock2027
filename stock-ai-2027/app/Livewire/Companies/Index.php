<?php

namespace App\Livewire\Companies;

use App\Exceptions\CompanySyncException;
use App\Models\Company;
use App\Services\CompanyQueryService;
use App\Services\CompanySyncService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $exchange = '';

    public string $industry = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    protected CompanyQueryService $companies;

    public function boot(CompanyQueryService $companies): void
    {
        $this->companies = $companies;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'exchange', 'industry'], true)) {
            $this->resetPage();
        }
    }

    public function sync(CompanySyncService $service): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        try {
            $count = $service->sync();
            $this->resetPage();
            $this->successMessage = 'Đã đồng bộ '.number_format($count).' công ty từ SSI.';
        } catch (CompanySyncException $exception) {
            $this->errorMessage = $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Không thể đồng bộ lúc này. Vui lòng thử lại sau.';
        }
    }

    public function render(): View
    {
        $query = $this->companies->query($this->search, $this->exchange, $this->industry);

        return view('livewire.companies.index', [
            'companies' => $query->paginate(25),
            'totalCompanies' => Company::count(),
            'exchanges' => Company::select('com_group_code')->distinct()->orderBy('com_group_code')->pluck('com_group_code'),
            'industries' => Company::select('icb_code')->distinct()->orderBy('icb_code')->pluck('icb_code'),
        ])->layout('components.layouts.app', ['title' => 'Công ty']);
    }
}
