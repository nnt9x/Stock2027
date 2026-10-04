<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListIcbsRequest;
use App\Http\Resources\IcbResource;
use App\Services\IcbQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IcbController extends Controller
{
    /**
     * Danh sách ngành ICB.
     *
     * Trả danh mục đang hoạt động theo code, mặc định 25 ngành mỗi trang.
     */
    public function index(ListIcbsRequest $request, IcbQueryService $service): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $icbs = $service->query()->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();

        return IcbResource::collection($icbs);
    }

    /**
     * Chi tiết ngành ICB theo code.
     *
     * Giữ số 0 đầu mã; trả 404 nếu ngành không tồn tại hoặc đã xoá mềm.
     */
    public function show(string $code, IcbQueryService $service): IcbResource
    {
        return new IcbResource($service->findByCode($code));
    }
}
