<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiveMetalRateService
{
    public function rates(): array
    {
        $targetSymbols = [
            'GOLD' => ['city' => 'Gold', 'decimals' => 2],
            'SILVER' => ['city' => 'Silver', 'decimals' => 2],
            'USD INR' => ['city' => 'Currency', 'decimals' => 3],
            'GLD 999 IMP AMD T+1' => ['city' => 'Ahmedabad', 'decimals' => 0],
            'GLD 999 IMP RJT T+1' => ['city' => 'Rajkot', 'decimals' => 0],
            'SLVCHORSA T+1' => ['city' => 'Silver', 'decimals' => 0],
            'SLVPETI999 T+1' => ['city' => 'Silver', 'decimals' => 0],
            'SLV 999 (1 KG BAR) T+1' => ['city' => 'Silver', 'decimals' => 0],
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(8)
                ->get('https://bcast.jksons.in:7768/VOTSBroadcastStreaming/Services/xml/GetLiveRateByTemplateID/jksons');

            if (!$response->successful()) {
                throw new \RuntimeException('JK Sons live rate feed returned HTTP ' . $response->status());
            }

            $previousRates = Cache::get('company_dashboard_jksons_rates_last', []);
            $rates = collect(preg_split('/\r\n|\r|\n/', trim($response->body())))
                ->map(function (string $line) {
                    $columns = preg_split('/\t+/', trim($line));

                    return [
                        'code' => $columns[0] ?? null,
                        'symbol' => $columns[1] ?? null,
                        'buy' => isset($columns[2]) ? (float) $columns[2] : null,
                        'rate' => isset($columns[3]) ? (float) $columns[3] : null,
                        'high' => isset($columns[4]) ? (float) $columns[4] : null,
                        'low' => isset($columns[5]) ? (float) $columns[5] : null,
                    ];
                })
                ->filter(fn (array $row) => isset($targetSymbols[$row['symbol'] ?? '']))
                ->map(function (array $row) use ($targetSymbols, $previousRates) {
                    $previous = $previousRates[$row['symbol']] ?? null;
                    $change = $previous !== null && $row['rate'] !== null ? $row['rate'] - (float) $previous : 0;
                    $meta = $targetSymbols[$row['symbol']];
                    $decimals = (int) ($meta['decimals'] ?? 0);

                    return [
                        'symbol' => $row['symbol'],
                        'city' => $meta['city'],
                        'rate' => $row['rate'],
                        'formatted_rate' => $row['rate'] !== null ? number_format((float) $row['rate'], $decimals, '.', '') : null,
                        'decimals' => $decimals,
                        'high' => $row['high'],
                        'low' => $row['low'],
                        'change' => $change,
                        'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
                        'available' => $row['rate'] !== null,
                    ];
                })
                ->sortBy(function (array $row) use ($targetSymbols) {
                    return array_search($row['symbol'], array_keys($targetSymbols), true);
                })
                ->values()
                ->all();

            Cache::put(
                'company_dashboard_jksons_rates_last',
                collect($rates)->pluck('rate', 'symbol')->all(),
                now()->addMinutes(10)
            );

            return [
                'enabled' => true,
                'message' => null,
                'source' => 'JK Sons',
                'rates' => $rates,
                'updated_at' => now()->format('d-m-Y h:i:s A'),
            ];
        } catch (\Throwable $exception) {
            Log::warning('JK Sons metal rate fetch failed', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'enabled' => false,
                'message' => 'Live city wise rates are not available.',
                'source' => 'JK Sons',
                'rates' => [],
                'updated_at' => null,
            ];
        }
    }
}
