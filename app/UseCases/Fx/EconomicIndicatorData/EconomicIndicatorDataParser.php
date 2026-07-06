<?php

declare(strict_types=1);

namespace App\UseCases\Fx\EconomicIndicatorData;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Normalizer;
use RuntimeException;
use Throwable;

// 経済指標カレンダーのテキストファイル（1行1指標、タブ区切り）をパースする。
// 解析できない行はエラーとしてログに残すのみでスキップし、ファイル全体は失敗させない（移植元 Java 版の実挙動に合わせる）。
class EconomicIndicatorDataParser
{
    public function parseFile(string $path, string $fileName, array $countryMap, array $indicatorMap): array
    {
        $importance = $this->getImportance($fileName);
        $year = $this->getYear($fileName);
        $baseDate = '';
        $resultList = [];

        foreach ($this->readLines($path) as $line) {
            $line = $this->applyStrip($line);

            if (preg_match('/^[0-9]{1,2}\/[0-9]{1,2}\(/u', $line)) {
                $baseDate = $this->toDate($year, $line);

                continue;
            }

            try {
                $data = $this->parseDataLine($baseDate, $importance, $line, $countryMap, $indicatorMap);
                if ($data !== null) {
                    $resultList[] = $data;
                }
            } catch (Throwable $e) {
                Log::error($line);
                Log::error($e->getMessage());
            }
        }

        return $resultList;
    }

    private function parseDataLine(string $baseDate, string $importance, string $line, array $countryMap, array $indicatorMap): ?array
    {
        if ($this->isSkip($line)) {
            return null;
        }

        if (! preg_match('/^[0-9]{2}:[0-9]{2}/', $line)) {
            if (str_contains($line, '日本')) {
                $line = "12:00\t{$line}";
            } elseif (str_contains($line, '中国')) {
                $line = "10:00\t{$line}";
            } elseif (str_contains($line, 'インド')) {
                $line = "21:00\t{$line}";
            }
        }

        if (! preg_match('/^[0-9]{2}:[0-9]{2}/', $line)) {
            return null;
        }

        $elem = explode("\t", $line);

        if (count($elem) < 7) {
            throw new RuntimeException("unexpected line format: {$line}");
        }

        if (! isset($countryMap[$elem[1]])) {
            throw new RuntimeException("country [{$elem[1]}] not found.");
        }

        $subTitle = $this->getSubTitle($elem[2]);
        $name = $this->normalizeIndicatorName($subTitle !== null ? str_replace($subTitle, '', $elem[2]) : $elem[2]);

        $countryIndicators = $indicatorMap[$elem[1]] ?? null;
        if ($countryIndicators === null || ! isset($countryIndicators[$name])) {
            throw new RuntimeException("economic-indicator [{$name}] not found.");
        }

        $indicator = $countryIndicators[$name];

        if ($importance !== $indicator['importance']) {
            Log::warning("diff importance file={$importance}|db={$indicator['importance']} -> baseDate={$baseDate}: {$line}");
        }

        $unitOfValue = $this->extractUnitOfValue($elem[6]);

        return [
            'code' => $indicator['code'],
            'countryCode' => $indicator['countryCode'],
            'publication' => $this->toPublication($baseDate, $elem[0]),
            'subTitle' => $subTitle,
            'previousValue' => $this->removeUnitOfValue($elem[4], $unitOfValue),
            'forecastValue' => $this->removeUnitOfValue($elem[5], $unitOfValue),
            'resultValue' => $this->removeUnitOfValue($elem[6] !== '' ? $elem[6] : '-', $unitOfValue),
        ];
    }

    private function applyStrip(string $line): string
    {
        return str_replace(config('fx.economic_indicator_data.indicator_strip_list'), '', $line);
    }

    private function isSkip(string $line): bool
    {
        foreach (config('fx.economic_indicator_data.indicator_exclude_list') as $keyword) {
            if (str_contains($line, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function getImportance(string $fileName): string
    {
        $parts = explode('_', $fileName);

        return explode('.', $parts[2])[0];
    }

    private function getYear(string $fileName): int
    {
        return (int) explode('_', $fileName)[0];
    }

    private function toDate(int $year, string $line): string
    {
        preg_match('/^([0-9]{1,2})\/([0-9]{1,2})\(/u', $line, $m);

        return Carbon::create($year, (int) $m[1], (int) $m[2])->format('Y-m-d');
    }

    // 発表時刻が24時以上の場合は翌日の時刻を表す（例: "25:30" -> 翌日 01:30）
    private function toPublication(string $baseDate, string $timeStr): string
    {
        preg_match('/^([0-9]{2}):([0-9]{2})/', $timeStr, $m);
        $hour = (int) $m[1];
        $minute = (int) $m[2];

        if ($hour > 23) {
            return Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %02d:%02d', $baseDate, $hour - 24, $minute))
                ->addDay()
                ->format('Y-m-d H:i:s');
        }

        return Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %02d:%02d', $baseDate, $hour, $minute))
            ->format('Y-m-d H:i:s');
    }

    private function normalizeIndicatorName(string $name): string
    {
        $name = str_replace(['、', '､', '・', '　', ' '], '', $name);

        return Normalizer::normalize($name, Normalizer::FORM_KC);
    }

    private function extractUnitOfValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = str_replace(['-', '+'], '', $value);
        $value = preg_replace('/[0-9]/', '', $value);

        return str_replace(['.', ' ', '(', ')'], '', $value);
    }

    private function removeUnitOfValue(string $value, string $unit): ?string
    {
        if ($unit === '') {
            return $value;
        }

        if ($value === '') {
            return null;
        }

        $value = str_replace($unit, '', $value);
        $value = str_replace(['％', '億円', '億元'], '', $value);

        return Normalizer::normalize($value, Normalizer::FORM_KC);
    }

    private function getSubTitle(string $indicatorName): ?string
    {
        if (preg_match('/^([0-9]{1,2}-[0-9]{1,2}月期)/u', $indicatorName, $m)) {
            return $m[1];
        }

        if (preg_match('/^([0-9]{1,2}月)/u', $indicatorName, $m)) {
            return $m[1];
        }

        return null;
    }

    private function readLines(string $path): array
    {
        $raw = file($path, FILE_IGNORE_NEW_LINES);
        if ($raw === false) {
            throw new RuntimeException("readLines error: {$path}");
        }

        $lines = [];
        foreach ($raw as $line) {
            if (str_starts_with($line, '(')) {
                $lines[count($lines) - 1] .= $line;
            } else {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
