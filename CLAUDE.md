# PHP Laravel RestAPI

PHP 8.3 / Laravel 11 で構築された RestAPI。

---

## このファイルの管理方針

**CLAUDE.md は「Claudeの行動を変える指示書」** であり、ドキュメントではない。
毎回コンテキストに全文読み込まれるため、肥大化させない。

| 種類 | 置き場所 |
|---|---|
| コーディング規約・禁止事項 | **CLAUDE.md** |
| アーキテクチャの制約（依存方向など） | **CLAUDE.md** |
| ビルド・実行コマンド | **CLAUDE.md** |
| 重要な落とし穴（DateTime、BigDecimal など） | **CLAUDE.md** |
| エンドポイント一覧 | `docs/api.md` |
| アーキテクチャ詳細・認証フロー・ディレクトリ構成 | `docs/architecture.md` |
| タスク指示（step 系） | **プロンプトで渡す** |

---

## ドキュメント参照先

| 内容 | ファイル |
|---|---|
| APIエンドポイント一覧・レスポンス仕様 | [docs/api.md](docs/api.md) |
| アーキテクチャ詳細・認証フロー・ディレクトリ構成 | [docs/architecture.md](docs/architecture.md) |
| VS Code 推奨設定・開発 Tips | [docs/tips.md](docs/tips.md) |

### 利用可能なカスタムコマンド

| コマンド | 用途 |
|---|---|
| `/docs-check` | ドキュメント（`docs/*.md`）と実装の乖離チェック。コミット前などに手動実行する |

---

## 外部ライブラリの最新情報

知識カットオフ（2025年8月）以降にリリースされたライブラリ・フレームワーク（Laravel 12.x など）に
関する回答は、WebSearch で公式ドキュメントを確認してから回答する。

---

## 言語設定

- 常に日本語で会話する
- コメントも日本語で記述する
- エラーメッセージの説明も日本語で行う

---

## Build & Run

```bash
# 依存関係インストール
composer install

# 環境変数設定（初回のみ）
cp .env.example .env
php artisan key:generate

# サーバー起動（ポート 8080）
php artisan serve --port=8080
```

### 環境変数ファイル

| ファイル | 用途 |
|---|---|
| `.env.example` | テンプレート（git 管理対象） |
| `.env` | 実際の値（git 除外済み） |

### ローカルインフラ起動

MySQL・Redis は `sandbox-tools` リポジトリで管理。DB スキーマ（DDL）も `sandbox-tools/docker/mysql/initdb.d/` で管理するため、このプロジェクトには `database/` フォルダおよびマイグレーションファイルを置かない。

```bash
# sandbox-tools/docker/ で実行
cd ../sandbox-tools/docker
cp .env.compose.example .env.compose  # 初回のみ
docker compose --env-file .env.compose up -d
```

---

## アーキテクチャ方針

**DDD（ドメイン駆動設計）・クリーンアーキテクチャは採用しない。**
Laravel の流儀（Active Record + UseCase）に従う。詳細は [docs/architecture.md](docs/architecture.md) 参照。

- Eloquent モデル（`app/Models/`）にドメインロジック（バリデーション・判定メソッド）を直接持たせる
- Repository インターフェースは設けない（Eloquent が Repository 相当）
- UseCase はビジネスロジックの置き場として維持する

### データフロー

```
Http/Controllers ──→ UseCases ──→ Models (Eloquent)
Http/Middleware  ──→ Services ──→ Models (Eloquent)
```

### 名前空間ルート

`App\`

---

## 実装規約

### 型宣言

全 PHP ファイルの先頭に `declare(strict_types=1);` を記述する。

### エラー型

| 例外クラス | HTTP ステータス |
|---|---|
| `AuthenticationException` | 401 UNAUTHORIZED |
| `ForbiddenException` | 403 FORBIDDEN |
| `NotFoundException` | 404 NOT FOUND |
| `DuplicateException` / `InsertException` / `UpdateException` | 400 BAD REQUEST |

例外クラスは `app/Exceptions/` に配置する。

### 日時フォーマット

- `Carbon` を使用し、ISO 8601 形式 `toIso8601String()` で返す（例: `2026-01-23T12:34:56+09:00`）
- Java の `LocalDateTime` に相当。`now()` は `Carbon::now()` で取得
- `LocalDate` 相当は `Carbon::now()->toDateString()`（`yyyy-MM-dd`）

### 小数・金額

PHP に `BigDecimal` はない。金額・価格・比率などの精度が必要な計算は `bcmath` 拡張を使う。

```php
$result = bcadd('0.1', '0.2', 10); // '0.3000000000'
```

DB の `DECIMAL` カラムは Eloquent で文字列として取得される。キャストに `'decimal:10'` を指定しない場合は文字列のまま扱う。

### userId のデコード

パスパラメータ `{userId}` は Base64 エンコード済み。`base64_decode($value, strict: true)` でデコードする。
userId・email はリクエストボディに含めず、`$request->attributes->get('authUser')` から取得する。

```php
$userId = base64_decode($userIdBase64, strict: true) ?: '';
```

### 管理者専用 API

```php
// routes/api.php
Route::middleware(['jwt.auth', 'role.admin'])->prefix('admin')->group(function (): void {
    // 管理者専用ルート
});
```

コントローラー内での個別チェックが必要な場合:

```php
$authUser = $request->attributes->get('authUser');
if (!$authUser->isAdmin()) {
    throw new ForbiddenException('管理者用APIです');
}
```

### 認証フロー（概要）

詳細は [docs/architecture.md](docs/architecture.md) 参照。

1. `JwtAuthMiddleware` — RS256 JWT 検証 → Redis から `AuthUser`（admin・approved フラグ含む）を取得 → `$request->attributes` にセット
2. `jwt.auth` ミドルウェアが `approved=false` のユーザーを 403 でブロック
   - `/v1/fx/master-list/**` — 認証不要（ミドルウェアなし）
   - 管理者専用エンドポイント — `role.admin` ミドルウェアで制御

### 環境変数

新しい環境変数を追加・削除・リネームしたら、`.env.example` の該当箇所も同時に更新する。

### コードフォーマット

Laravel Pint を使用。

```bash
./vendor/bin/pint
```
