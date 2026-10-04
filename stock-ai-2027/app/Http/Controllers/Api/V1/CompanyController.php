<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CompanySyncException;
use App\Exceptions\CompanySyncInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCompaniesRequest;
use App\Http\Resources\CompanyResource;
use App\Services\CompanyQueryService;
use App\Services\CompanySyncService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    /**
     * Danh sách công ty.
     *
     * Lọc theo tiền tố ticker (search), sàn (com_group_code), ngành ICB (icb_code).
     * Kết quả phân trang và không bao gồm công ty đã xoá mềm.
     */
    public function index(ListCompaniesRequest $request, CompanyQueryService $service): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $companies = $service->query(
            $filters['search'] ?? '',
            $filters['com_group_code'] ?? '',
            $filters['icb_code'] ?? '',
        )->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();

        return CompanyResource::collection($companies);
    }

    /**
     * Chi tiết công ty theo ticker.
     *
     * Trả về 404 nếu mã không tồn tại hoặc công ty đã xoá mềm.
     */
    public function show(string $ticker, CompanyQueryService $service): CompanyResource
    {
        return new CompanyResource($service->findByTicker($ticker));
    }

    /**
     * Đồng bộ công ty từ SSI.
     *
     * Thêm mới hoặc cập nhật theo ticker, giữ nguyên trạng thái xoá mềm.
     * Chạy trực tiếp; giới hạn 2 lần/phút. Trả về 409 khi đang đồng bộ và 502 khi nguồn SSI lỗi.
     */
    public function sync(CompanySyncService $service): JsonResponse
    {
        try {
            $count = $service->sync();
        } catch (CompanySyncInProgressException $exception) {
            return response()->json(['message' => (string) $exception->getMessage()], 409);
        } catch (CompanySyncException $exception) {
            return response()->json(['message' => (string) $exception->getMessage()], 502);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'Không thể kết nối hoặc tải dữ liệu từ SSI. Vui lòng thử lại sau.'], 502);
        }

        return response()->json([
            'message' => 'Đồng bộ công ty thành công.',
            'data' => ['synced' => $count],
        ]);
    }
}
