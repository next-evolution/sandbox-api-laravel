# Tips

## VS Code 設定（`.vscode/settings.json`）

PHP / Laravel 開発向けの推奨設定。

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "bmewburn.vscode-intelephense-client",
  "[php]": {
    "editor.tabSize": 4
  },
  "files.exclude": {
    "**/vendor": true
  },
  "search.exclude": {
    "**/vendor": true
  },
  "intelephense.environment.phpVersion": "8.3",
  "intelephense.files.exclude": [
    "**/vendor/**"
  ]
}
```

---

## 推奨 VS Code 拡張機能（PHP / Laravel 関連）

| 拡張機能 ID | 名前 | 説明 |
|---|---|---|
| `bmewburn.vscode-intelephense-client` | PHP Intelephense | PHP の型補完・定義ジャンプ・リファクタリング基盤 |
| `onecentlin.laravel-blade` | Laravel Blade Snippets | Blade テンプレートの補完（API 専用なら不要） |
| `amiralizadeh9480.laravel-extra-intellisense` | Laravel Extra Intellisense | Route・Config・View の補完 |
| `ryannaddy.laravel-artisan` | Laravel Artisan | Artisan コマンドを VS Code から実行 |
| `mikestead.dotenv` | DotENV | `.env` ファイルのシンタックスハイライト |
| `redhat.vscode-yaml` | YAML | `api-docs.yaml` などの編集 |

---

## よく使う Artisan コマンド

```bash
# 開発サーバー起動
php artisan serve --port=8080

# マイグレーション
php artisan migrate
php artisan migrate:fresh          # 全テーブル削除して再作成
php artisan migrate:status         # マイグレーション状態確認

# キャッシュクリア
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# ルート一覧
php artisan route:list

# コードフォーマット（Laravel Pint）
./vendor/bin/pint
./vendor/bin/pint --test            # チェックのみ（変更なし）
```

---

## PHP / Laravel 固有の注意事項

### BigDecimal がない

Java の `BigDecimal` に相当するものがない。金額・価格・比率など精度が必要な計算は `bcmath` 拡張を使う。

```php
bcadd('0.1', '0.2', 10);   // '0.3000000000'
bcmul('100.5', '1.05', 4); // '105.5250'
```

DB の `DECIMAL` カラムは Eloquent が文字列として返すため、`float` にキャストしないこと。

### Carbon（日時）

Java の `LocalDateTime` / `LocalDate` に相当するのが `Carbon\Carbon`。

```php
Carbon::now()                          // LocalDateTime.now()
Carbon::parse('2026-01-23 12:34:56')   // LocalDateTime.parse()
$carbon->toIso8601String()             // DateTimeFormatter.ISO_OFFSET_DATE_TIME
$carbon->toDateString()                // LocalDate.toString()
```

### base64_decode の厳格モード

Base64 デコードは必ず `strict: true` を付ける。付けないと不正な文字を含む文字列でも `false` を返さず誤動作する。

```php
$decoded = base64_decode($encoded, strict: true);
if ($decoded === false) {
    throw new DomainValidationException('Invalid BASE64');
}
```

### DB_HOST は 127.0.0.1 を使う

`DB_HOST=localhost` にすると PHP が Unix ドメインソケット接続を試みて Docker ポートフォワード経由で接続できない。必ず `127.0.0.1` を使う。

### Eloquent の timestamps 自動管理

`sandbox_user` テーブルは `created_at` / `updated_at` を手動管理（`created_by` / `updated_by` も持つため）。  
`SandboxUser` モデルでは `public $timestamps = false;` を設定し、リポジトリで明示的にセットする。

---

## トラブルシューティング

### `tymon/jwt-auth` が干渉する

`tymon/jwt-auth` パッケージが自動検出されて `JwtAuthMiddleware` よりも先に動作する場合がある。
このプロジェクトでは `firebase/php-jwt` を直接使うため、`tymon/jwt-auth` は削除済み。
もし再インストールされた場合は `composer remove tymon/jwt-auth` で削除する。

### F12（定義元へ移動）が動かない

1. `Cmd+Shift+X` → `PHP Intelephense` が有効か確認
2. `Cmd+Shift+P` → `Intelephense: Index workspace` でインデックス再構築
3. `vendor/` が `intelephense.files.exclude` に含まれているか確認

### Redis 接続エラー

```
Connection refused [tcp://127.0.0.1:46379]
```

Docker が起動していない場合。`sandbox-tools/docker/` で `docker compose up -d` を実行する。
