<?php

namespace Tests\Feature;

use App\Integrations\Indicators\PythonIndicatorClient;
use App\Jobs\CalculateIndicatorsJob;
use App\Jobs\SyncOhlcvJob;
use App\Models\Company;
use App\Models\OhlcvSyncState;
use App\Services\IndicatorBatchService;
use App\Services\OhlcvBatchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class IndicatorPipelineTest extends TestCase
{
    use RefreshDatabase;

    /** Khi cả hai job giá kết thúc, callback tạo đúng một batch chỉ báo và liên kết nguồn. */
    public function test_price_completion_dispatches_indicator_batch_once(): void
    {
        config(['queue.default' => 'database']);
        $this->travelTo(CarbonImmutable::parse('2024-01-03 17:00', 'Asia/Ho_Chi_Minh'));
        Company::factory()->create(['ticker' => 'ACB']);
        Http::preventStrayRequests();
        Http::fake(['api.dnse.com.vn/*' => Http::response(['t' => [], 'o' => [], 'h' => [], 'l' => [], 'c' => [], 'v' => []])]);
        $price = app(OhlcvBatchService::class)->dispatch('ACB');
        $this->artisan('queue:work database --queue=ohlcv --once --sleep=0')->assertSuccessful();
        $this->assertArrayNotHasKey('indicator_batch_id', $price->fresh()->options);
        $this->assertNull(app(IndicatorBatchService::class)->dispatchForPriceBatch($price->id));
        $this->artisan('queue:work database --queue=ohlcv --once --sleep=0')->assertSuccessful();
        $indicator = Bus::findBatch($price->fresh()->options['indicator_batch_id']);
        $this->assertSame($price->id, $indicator->options['price_batch_id']);
        $this->assertSame(2, $indicator->totalJobs);
        $this->assertSame(2, DB::table('jobs')->where('queue', 'indicators')->count());
        $again = app(IndicatorBatchService::class)->dispatchForPriceBatch($price->id);
        $this->assertSame($indicator->id, $again->id);
        $this->assertDatabaseCount('job_batches', 2);
        Http::assertSentCount(2);
    }

    /** Batch giá có lỗi vẫn tạo chỉ báo cho chuỗi thành công, bỏ chuỗi không có kết quả của lượt này. */
    public function test_price_failure_does_not_block_successful_symbols(): void
    {
        Queue::fake([SyncOhlcvJob::class, CalculateIndicatorsJob::class]);
        $price = Bus::batch([new SyncOhlcvJob('ACB', '1D', 1791103422), new SyncOhlcvJob('CTC', '1D', 1791103422)])
            ->name('OHLCV test')->withOption('until', 1791103422)->withOption('tickers', ['ACB', 'CTC'])->allowFailures()->dispatch();
        OhlcvSyncState::create(['ticker' => 'ACB', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422, 'data_version' => 1]);
        OhlcvSyncState::create(['ticker' => 'CTC', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422, 'last_error' => 'invalid symbol']);
        $price->recordSuccessfulJob('success-job');
        $price->recordFailedJob('failed-job', new RuntimeException('invalid symbol'));
        $indicator = app(IndicatorBatchService::class)->dispatchForPriceBatch($price->id);
        $this->assertSame(1, $indicator->totalJobs);
        Queue::assertPushed(CalculateIndicatorsJob::class, fn ($job) => $job->ticker === 'ACB' && $job->priceBatchId === $price->id);
        Queue::assertPushed(CalculateIndicatorsJob::class, 1);
    }

    /** Lỗi HTTP Python không được báo là job đã hoàn thành; payload giữ nguyên để retry an toàn. */
    public function test_job_calls_python_and_accepts_only_completed_result(): void
    {
        config(['services.indicators.token' => 'test-secret', 'services.indicators.base_url' => 'http://indicator.test']);
        OhlcvSyncState::create(['ticker' => 'ACB', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422, 'data_version' => 3]);
        OhlcvSyncState::create(['ticker' => 'VNINDEX', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422, 'data_version' => 5]);
        Http::preventStrayRequests();
        $payloads = [];
        Http::fake(['indicator.test/*' => function (Request $request) use (&$payloads) {
            $payloads[] = $request->data();

            return Http::response(['status' => 'completed', 'request_id' => $request['request_id'], 'ticker' => 'ACB',
                'resolution' => '1D', 'until' => 1791103422, 'rows_processed' => 683]);
        }]);
        $job = new CalculateIndicatorsJob('ACB', '1D', 1791103422, 'test-run');
        $job->handle(app(PythonIndicatorClient::class));
        $job->handle(app(PythonIndicatorClient::class));
        $this->assertSame($payloads[0]['request_id'], $payloads[1]['request_id']);
        $this->assertSame(3, $payloads[0]['source_version']);
        $this->assertSame(5, $payloads[0]['benchmark_version']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-secret'));
        Http::assertSentCount(2);
    }

    /** HTTP 202 chỉ nhận yêu cầu chưa đủ để Laravel công nhận đã tính xong. */
    public function test_accepted_but_not_completed_response_is_rejected(): void
    {
        config(['services.indicators.token' => 'test-secret', 'services.indicators.base_url' => 'http://indicator.test']);
        Http::preventStrayRequests();
        Http::fake(['indicator.test/*' => Http::response(['status' => 'accepted'], 202)]);
        try {
            app(PythonIndicatorClient::class)->calculate(['request_id' => 'x', 'ticker' => 'ACB', 'resolution' => '1D', 'until' => 1791103422]);
            $this->fail('Không được coi 202 là đã tính xong.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('chưa xác nhận', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    /** Benchmark không có kết quả thành công của lượt giá thì không dùng giá benchmark cũ. */
    public function test_failed_benchmark_is_explicitly_missing(): void
    {
        config(['services.indicators.token' => 'test-secret', 'services.indicators.base_url' => 'http://indicator.test']);
        OhlcvSyncState::create(['ticker' => 'ACB', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422, 'data_version' => 3]);
        OhlcvSyncState::create(['ticker' => 'VNINDEX', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422, 'data_version' => 5]);
        Http::preventStrayRequests();
        Http::fake(['indicator.test/*' => fn (Request $request) => Http::response([
            'status' => 'completed', 'request_id' => $request['request_id'], 'ticker' => 'ACB',
            'resolution' => '1D', 'until' => 1791103422, 'rows_processed' => 683,
        ])]);
        (new CalculateIndicatorsJob('ACB', '1D', 1791103422, 'test-run', 'price-with-failed-benchmark'))->handle(app(PythonIndicatorClient::class));
        Http::assertSent(fn (Request $request) => $request['benchmark_version'] === null);
    }

    /** Chuỗi chưa lưu xong giá hoặc đang chờ full reload không được gọi Python. */
    public function test_pending_price_reload_does_not_call_python(): void
    {
        OhlcvSyncState::create(['ticker' => 'ACB', 'resolution' => '1D', 'synced_through_timestamp' => 1791103422,
            'reload_version' => 2, 'completed_reload_version' => 1]);
        Http::preventStrayRequests();
        Http::fake(['indicator.test/*' => Http::response([])]);
        try {
            (new CalculateIndicatorsJob('ACB', '1D', 1791103422, 'test-run'))->handle(app(PythonIndicatorClient::class));
            $this->fail('Không được tính khi giá còn đang tải lại.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('chưa sẵn sàng', $exception->getMessage());
        }
        Http::assertNothingSent();
    }
}
