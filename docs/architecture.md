# アーキテクチャ詳細

PHP 8.3 / Laravel 11 シングルアプリ構成。Laravel の流儀（Active Record + UseCase）に従う。

**DDD（ドメイン駆動設計）・クリーンアーキテクチャは採用しない。**

> **採用しない理由**: Laravel の Eloquent は Active Record パターンであり、DDD のリポジトリパターンと設計思想が競合する。
> Repository インターフェース・ドメインモデルの二重管理はLaravelの恩恵（スコープ・リレーション等）を損ない、ボイラープレートが増えるだけで実質的なメリットがない。

---

## ディレクトリ構成

| ディレクトリ | 役割 |
|---|---|
| `app/Exceptions/` | アプリ例外 7 種（`RuntimeException` のサブクラス） |
| `app/Models/AuthUser.php` | JWT セッション用オブジェクト（DB 非依存）。JWT クレームと admin・approved フラグを保持 |
| `app/Models/SandboxUser.php` | Eloquent モデル（`sandbox_user` テーブル）。ドメインロジックを直接持つ |
| `app/Models/Fx*.php` | FX 系 Eloquent モデル（`FxSymbol`, `FxCountry`, `FxSummerTime`, `FxEconomicIndicator`）。各テーブルに 1 対 1 対応 |
| `app/Services/JwtService.php` | Cognito RS256 JWKS 検証 |
| `app/Services/SessionService.php` | Redis セッション管理（`AuthUser` の保存・取得・削除） |
| `app/Services/MasterCacheService.php` | Redis マスターデータキャッシュ管理（`master:*` キーの取得・保存・ステータス集計・パターン削除） |
| `app/UseCases/` | ビジネスロジック。各クラスは `execute()` メソッドを持つ。ドメイン別サブディレクトリ（`Auth/`, `User/`, `Fx/Symbol/` など）で整理 |
| `app/Http/Controllers/` | REST コントローラー。リクエスト取得・バリデーション・UseCase 呼び出し・レスポンス生成のみ。ドメイン別サブディレクトリ（`Fx/` など）で整理 |
| `app/Http/Middleware/` | `JwtAuthMiddleware`、`AdminMiddleware`、`JsonUnescapedUnicodeMiddleware`（全APIレスポンスに `JSON_UNESCAPED_UNICODE` を適用、`api` グループに一括登録） |
| `bootstrap/app.php` | ルーティング・ミドルウェアエイリアス・例外ハンドラ登録 |

### データフロー

```
Http/Controllers ──→ UseCases ──→ Models (Eloquent)
Http/Middleware  ──→ Services ──→ Models (Eloquent)
```

---

## 認証フロー

1. 全リクエストがルートミドルウェアを通過
   - `jwt.auth`（`JwtAuthMiddleware`）が適用されたルートのみ検証
   - Bearer トークンを `Authorization` ヘッダーから取得
   - `JwtService::parse()` で RS256 JWT を検証（JWKS は Redis にキャッシュ、TTL 1 時間）
   - Redis から `AuthUser`（admin・approved フラグ含む）を取得
     - **セッションあり**: Redis の `AuthUser` を使い TTL をリセット
     - **セッションなし**: `sandbox_user` テーブルから `AuthUser` を復元して Redis に保存（silent login）
     - **DB にも存在しない**: 403 FORBIDDEN を返す
   - `approved=false` のユーザーは 403 FORBIDDEN を返す
   - `$request->attributes->set('authUser', $authUser)` にセット
2. ルート定義でミドルウェアを制御（`routes/api.php`）
   - `middleware('jwt.auth')` — 承認済みユーザーのみ通過
   - `middleware(['jwt.auth', 'role.admin'])` — 管理者のみ通過
   - ミドルウェアなし — 認証不要（`/v1/fx/master-list/**`）
3. `POST /api/v1/auth/login` — JWT のメール情報と Base64 デコードしたリクエストボディのメールを照合 → DB から `SandboxUser` を取得 → `AuthUser` を Redis に保存

---

## AuthUser

`AuthUser`（`app/Models/AuthUser.php`）は JWT クレームと DB の admin・approved フラグを保持するクラス。DB 非依存の純粋な PHP クラス。

- フィールド: `sub`, `email`, `emailVerified`, `admin`, `approved`（全て `readonly`）
- `isAdmin()` — 管理者判定、`isApproved()` — 承認済み判定
- `toArray()` / `fromArray()` — Redis JSON シリアライズ用
- `JwtService::parse()` では JWT から admin/approved 情報を得られないため `admin=false, approved=false` で生成し、`JwtAuthMiddleware` で Redis または DB から正しいフラグ付き `AuthUser` を上書き取得する

---

## SandboxUser（Eloquent モデル）

`SandboxUser`（`app/Models/SandboxUser.php`）は `sandbox_user` テーブルの Eloquent モデル。ドメインロジックも直接持つ。

- `$timestamps = false` — `created_at`/`updated_at` を手動管理（`created_by`/`updated_by` も存在するため）
- ドメインメソッド: `isApproved()`, `isAdmin()`, `checkBlocked()`, `checkAlreadyApproved()`, `checkBlockDuplicate()`, `checkAdminDuplicate()`
- `toDtoArray()` — API レスポンス用 camelCase 配列を生成

---

## DB スキーマ管理

テーブル定義（DDL）は複数プロジェクトで共通利用するため、`sandbox-tools` リポジトリで一元管理。

- `sandbox-tools/docker/mysql/initdb.d/` に SQL ファイルを配置
- Docker コンテナ起動時（`docker compose up`）に自動実行される
- このプロジェクトには `database/` フォルダおよびマイグレーションファイルを置かない

---

## Redis 利用

- **セッション**: `SessionService` で管理
  - キー: `session:{sub}`、TTL: `.env` の `SESSION_LIFETIME` 分（デフォルト 30 分）
  - シリアライズ: `AuthUser::toArray()` で JSON 保存
- **JWKS キャッシュ**: `JwtService` が Cognito の公開鍵を Redis にキャッシュ（TTL 1 時間）
  - キー: `jwks`
- **マスターデータキャッシュ**: `MasterCacheService` によるキャッシュアサイドパターン
  - キー: `master:{name}`（例: `master:country`, `master:symbol_Trade`, `master:economic_indicator_JP`）、TTL なし（`PUT /v1/admin/master-refresh` による明示的リフレッシュまで保持）
  - `GET /v1/admin/master-refresh` — 各 `master:*` キーの件数を `key=count` 形式（改行区切り）で返す
  - `PUT /v1/admin/master-refresh` — 国・シンボル（Trade/Analyze）・国別経済指標のキャッシュを再構築し、`price*` パターンのキーを削除した上でステータスを返す

---

## 例外ハンドリング

`bootstrap/app.php` の `withExceptions()` で全例外を HTTP レスポンスにマッピング。

| 例外クラス | HTTP ステータス | レスポンス形式 |
|---|---|---|
| `AuthenticationException` | 401 | `{"status": 401, "statusText": "UNAUTHORIZED", "message": "..."}` |
| `ForbiddenException` | 403 | `{"status": 403, "statusText": "FORBIDDEN", "message": "..."}` |
| `NotFoundException` | 404 | `{"status": 404, "statusText": "NOT_FOUND", "message": "..."}` |
| `DomainValidationException` / `DuplicateException` / `InsertException` / `UpdateException` | 400 | `{"status": 400, "statusText": "BAD_REQUEST", "message": "..."}` |

ミドルウェアが直接返す 401/403 も同じ JSON 形式。

---

## API 規約

- ベースパス: `/api`（Laravel の api ルートプレフィックス）、バージョニング: `/v1/...`
- 基本レスポンスは `returnCode`（整数）を含む JSON
- マスター系・一部操作系は生配列 / オブジェクト / 空ボディ（200 OK）を返す（詳細は `docs/api.md` 参照）
- `declare(strict_types=1)` を全ファイルに記述
