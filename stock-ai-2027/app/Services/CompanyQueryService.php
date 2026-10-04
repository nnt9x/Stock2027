<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

class CompanyQueryService
{
    /** @return Builder<Company> */
    public function query(string $search = '', string $exchange = '', string $industry = ''): Builder
    {
        $ticker = mb_strtoupper(trim($search));

        return Company::query()
            ->when($ticker !== '', fn (Builder $query): Builder => $query->where('ticker', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $ticker).'%'))
            ->when($exchange !== '', fn (Builder $query): Builder => $query->where('com_group_code', $exchange))
            ->when($industry !== '', fn (Builder $query): Builder => $query->where('icb_code', $industry))
            ->orderBy('ticker');
    }

    public function findByTicker(string $ticker): Company
    {
        return Company::where('ticker', mb_strtoupper(trim($ticker)))->firstOrFail();
    }
}
