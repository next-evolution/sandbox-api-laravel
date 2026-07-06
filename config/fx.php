<?php

declare(strict_types=1);

return [
    'bar_data' => [
        // true の場合、CSVインポート時の実データとの差分チェック（登録件数・件数不一致判定）をスキップする
        'import_check_skip' => (bool) env('FX_BAR_DATA_IMPORT_CHECK_SKIP', true),
        // CSVパース結果を load テーブルへバルク insert する際の1回あたりの行数
        'csv_bulk_load_size' => (int) env('FX_BAR_DATA_CSV_BULK_LOAD_SIZE', 1000),
    ],
    'economic_indicator_data' => [
        // テキストインポート時、指標名から除去する文字列（人名の変更などで指標名に混入するケース）
        'indicator_strip_list' => [
            '植田和男',
            '黒田東彦',
        ],
        // テキストインポート時、この文字列を含む行をスキップする
        'indicator_exclude_list' => [
            '本日掲載の指標はありません',
            '休場',
            '韓国',
            'ポーランド',
            '日銀・金融政策決定会合（',
        ],
    ],
];
