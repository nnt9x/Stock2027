<?php

namespace App\Integrations\Ssi;

use App\Exceptions\CompanySyncException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SsiCompanyClient
{
    /** @return list<array{ticker: string, comGroupCode: string, icbCode: string, organName: string, organShortName: string}> */
    public function organizations(): array
    {
        $payload = Http::acceptJson()
            ->withHeaders([
                'Accept-Language' => 'vi,en;q=0.9',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 Edg/131.0.0.0',
                'Origin' => 'https://iboard.ssi.com.vn',
                'Referer' => 'https://iboard.ssi.com.vn/',
                'x-fiin-user-token' => config('services.ssi.token'),
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 250, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)))
            ->get(rtrim(config('services.ssi.base_url'), '/').'/Master/GetListOrganization', ['language' => 'vi'])
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new CompanySyncException('API SSI trả về dữ liệu không hợp lệ.');
        }

        $validator = Validator::make($payload, [
            'status' => ['required', 'in:Success'],
            'totalCount' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'list', 'min:1'],
            'items.*' => ['required', 'array'],
            'items.*.ticker' => ['required', 'string', 'max:255'],
            'items.*.comGroupCode' => ['required', 'string', 'max:255'],
            'items.*.icbCode' => ['required', 'string', 'max:255'],
            'items.*.organName' => ['required', 'string', 'max:255'],
            'items.*.organShortName' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails() || count($payload['items']) !== (int) $payload['totalCount']) {
            throw new CompanySyncException('Danh sách công ty từ SSI không đầy đủ hoặc không hợp lệ.');
        }

        $tickers = array_map(fn (array $item): string => mb_strtoupper(trim($item['ticker'])), $payload['items']);

        if (in_array('', $tickers, true) || count(array_unique($tickers)) !== count($tickers)) {
            throw new CompanySyncException('Danh sách SSI có mã cổ phiếu rỗng hoặc trùng lặp.');
        }

        return $payload['items'];
    }
}
