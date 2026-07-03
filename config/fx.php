<?php

declare(strict_types=1);

return [
    'bar_data' => [
        // true の場合、CSVインポート時の実データとの差分チェック（登録件数・件数不一致判定）をスキップする
        'import_check_skip' => (bool) env('FX_BAR_DATA_IMPORT_CHECK_SKIP', true),
        // CSVパース結果を load テーブルへバルク insert する際の1回あたりの行数
        'csv_bulk_load_size' => (int) env('FX_BAR_DATA_CSV_BULK_LOAD_SIZE', 1000),
    ],
];
