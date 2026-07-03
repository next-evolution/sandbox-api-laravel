<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\DomainValidationException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FxBarData extends Model
{
    public $timestamps = false;

    protected $casts = [
        'bar_date_time' => 'datetime',
    ];

    private const TABLE_SUFFIX_BY_BAR_TYPE = [
        'M15' => '15m',
        'H1' => '1h',
        'H4' => '4h',
        'D1' => '1d',
    ];

    // CSVファイル名に含まれる足種別キーワード（例: USDJPY_240_xxxx.csv）
    private const CSV_KEYWORD_BY_BAR_TYPE = [
        'M15' => '15',
        'H1' => '60',
        'H4' => '240',
        'D1' => '1D',
    ];

    public static function tableForBarType(string $barType): string
    {
        return 'fx_bar_'.self::suffixForBarType($barType);
    }

    public static function suffixForBarType(string $barType): string
    {
        return self::TABLE_SUFFIX_BY_BAR_TYPE[$barType] ?? throw new DomainValidationException("不正な barType です: {$barType}");
    }

    public static function csvKeywordForBarType(string $barType): string
    {
        return self::CSV_KEYWORD_BY_BAR_TYPE[$barType] ?? throw new DomainValidationException("不正な barType です: {$barType}");
    }

    // D1 は日付のみ（例: 2026-03-02）、それ以外はオフセット付き日時（例: 2026-03-02T07:00:00+09:00）。
    // オフセットはタイムゾーン変換に使わず、CSVに書かれた時刻をそのまま採用する。
    public static function parseCsvBarDateTime(string $barType, string $raw): string
    {
        if ($barType === 'D1') {
            return $raw.' 00:00:00';
        }

        return Carbon::parse($raw)->format('Y-m-d H:i:s');
    }

    public function toDtoArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'barDateTime' => $this->bar_date_time?->toIso8601String(),
            'openPrice' => (float) $this->open_price,
            'highPrice' => (float) $this->high_price,
            'lowPrice' => (float) $this->low_price,
            'closePrice' => (float) $this->close_price,
            'volume' => (int) $this->volume,
            'highProfit' => (float) $this->high_profit,
            'lowProfit' => (float) $this->low_profit,
            'closeProfit' => (float) $this->close_profit,
            'rangeProfit' => (float) $this->range_profit,
            'rsiValue' => $this->rsi_value !== null ? (float) $this->rsi_value : null,
            'rsiMa' => $this->rsi_ma !== null ? (float) $this->rsi_ma : null,
        ];
    }
}
