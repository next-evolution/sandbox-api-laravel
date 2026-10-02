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
| `app/Services/BearerTokenResolver.php` | `Authorization: Bearer` ヘッダーからのトークン取り出し（Bearerリクエスト判定にも使用） |
| `app/Services/JwtCookieService.php` | Web向け `sandbox_jwt` Cookie の発行・失効（`Set-Cookie` 用 `Cookie` オブジェクト生成） |
| `app/Services/SessionService.php` | Redis セッション管理（`AuthUser` の保存・取得・削除） |
| `app/Services/MasterCacheService.php` | Redis マスターデータキャッシュ管理（`master:*` キーの取得・保存・ステータス集計・パターン削除） |
| `app/UseCases/` | ビジネスロジック。各クラスは `execute()` メソッドを持つ。ドメイン別サブディレクトリ（`Auth/`, `User/`, `Fx/Symbol/` など）で整理 |
| `app/Http/Controllers/` | REST コントローラー。リクエスト取得・バリデーション・UseCase 呼び出し・レスポンス生成のみ。ドメイン別サブディレクトリ（`Fx/` など）で整理 |
| `app/Http/Middleware/` | `JwtAuthMiddleware`（JWT認証＋承認判定）、`CsrfCookieMiddleware`（Cookie方式のCSRF対策）、`AdminMiddleware`、`JsonUnescapedUnicodeMiddleware`（全APIレスポンスに `JSON_UNESCAPED_UNICODE` を適用）。`CsrfCookieMiddleware`・`JsonUnescapedUnicodeMiddleware` は `api` グループに一括登録 |
| `config/cors.php` | React（別オリジン配信）向け CORS 設定（`CORS_ORIGIN1`/`CORS_ORIGIN2`、`supports_credentials: true`） |
| `bootstrap/app.php` | ルーティング・ミドルウェアエイリアス・例外ハンドラ登録 |

### データフロー

```
Http/Controllers ──→ UseCases ──→ Models (Eloquent)
Http/Middleware  ──→ Services ──→ Models (Eloquent)
```

---

## 認証フロー

1. 全リクエストが `JwtAuthMiddleware`（`jwt.auth`）を通過（適用ルートのみ）
   - トークン取得: `Authorization: Bearer`（Flutter）を優先し、無ければ `sandbox_jwt` Cookie（React）を見る（`resolveToken()`）
   - `JwtService::parse()` で RS256 JWT を検証（JWKS は Redis にキャッシュ、TTL 1 時間）。ここでは認可判定は行わない
   - `resolveAuthUser()` で `AuthUser`（admin・approved フラグ含む）を解決
     - **セッションあり**: Redis の `AuthUser` を使い TTL をリセット
     - **セッションなし**: `sandbox_user` テーブルから `AuthUser` を復元して Redis に保存（silent login）
     - **DB にも存在しない**: JWT クレーム由来の `AuthUser`（admin=false, approved=false）にフォールバックする（403 にはしない）
   - `$request->attributes->set('authUser', $authUser)` にセット
   - ミドルウェアパラメータ `mode`（デフォルト `approved`）で認可判定を制御
     - `mode=approved`（デフォルト、`middleware('jwt.auth')`）: `approved=false` のユーザーは 403 FORBIDDEN
     - `mode=any`（`middleware('jwt.auth:any')`）: 承認判定をスキップし、未承認・未登録でも到達させる。ログインAPI（`/v1/auth/login/web`・`/app`）と `POST`/`GET /v1/user` の3エンドポイントのみに使用し、到達後はコントローラ/UseCase側で個別に承認待ち等を判定する
2. `CsrfCookieMiddleware`（`api` グループ全体に適用）
   - GET/HEAD/OPTIONS 以外かつ Bearer リクエストでない場合、`XSRF-TOKEN` Cookie と `X-XSRF-TOKEN` ヘッダーが一致しなければ 403 FORBIDDEN（ダブルサブミットCookie）
   - 全レスポンスで `XSRF-TOKEN` Cookie を発行・更新する（`Path=/`・`Secure`・`SameSite=None`・`HttpOnly=false`）
   - ログインAPIは常に Bearer リクエストとして呼ばれるため、この検証は自然にスキップされる
3. ルート定義でミドルウェアを制御（`routes/api.php`）
   - `middleware('jwt.auth:any')` — ログインAPI・`POST`/`GET /v1/user`
   - `middleware('jwt.auth')` — その他の承認済みユーザー向けルート
   - `middleware(['jwt.auth', 'role.admin'])` — 管理者のみ通過
   - ミドルウェアなし — 認証不要（`/v1/fx/master-list/**`）
4. `POST /api/v1/auth/login/web`・`POST /api/v1/auth/login/app` — JWT のメール情報と Base64 デコードしたリクエストボディのメールを照合 → DB から `SandboxUser` を取得 → `AuthUser` を Redis に保存。`login/web` は成功時に `JwtCookieService::buildLoginCookie()` で JWT を `sandbox_jwt` Cookie として `Set-Cookie` する（`login/app` は行わない）
5. `POST /api/v1/auth/logout-api` — Redis セッション削除に加え、`JwtCookieService::buildLogoutCookie()` で `sandbox_jwt` Cookie を失効させる

---

## AuthUser

`AuthUser`（`app/Models/AuthUser.php`）は JWT クレームと DB の admin・approved フラグを保持するクラス。DB 非依存の純粋な PHP クラス。

- フィールド: `sub`, `email`, `emailVerified`, `admin`, `approved`（全て `readonly`）
- `isAdmin()` — 管理者判定、`isApproved()` — 承認済み判定
- `toArray()` / `fromArray()` — Redis JSON シリアライズ用
- `JwtService::parse()` では JWT から admin/approved 情報を得られないため `admin=false, approved=false` で生成し、`JwtAuthMiddleware` で Redis または DB から正しいフラグ付き `AuthUser` を上書き取得する（DB にも存在しない場合はこのプレースホルダーのままフォールバックする）

---

## CORS / CSRF（Cookie方式・Web向け）

sandbox-spa-react は本番で別オリジン配信されるため、Cookie の送受信には明示的な CORS 設定と CSRF 対策が必要（詳細仕様は `documents/architecture/auth.md`「トークン受け渡し方式（クライアント種別）」参照）。

- **CORS**（`config/cors.php`）: `CORS_ORIGIN1`/`CORS_ORIGIN2` を許可オリジンとして明示し、`supports_credentials: true` を設定（Cookie利用時、Originのワイルドカード指定はブラウザ仕様上使えない）
- **CSRF**（`CsrfCookieMiddleware`、`api` グループに一括登録）: ダブルサブミットCookie方式
  - 全レスポンスで `XSRF-TOKEN` Cookie（`Path=/`・`Secure`・`SameSite=None`・`HttpOnly=false`）を発行・更新する
  - GET/HEAD/OPTIONS 以外の Cookie 認証リクエスト（`Authorization: Bearer` ヘッダーが無いリクエスト）は `X-XSRF-TOKEN` ヘッダーと `XSRF-TOKEN` Cookie の値が一致しなければ 403 FORBIDDEN
  - Bearer方式（Flutter）はこの検証をスキップする
  - ログインAPI（`/v1/auth/login/web`・`/app`）自体は常に `Authorization: Bearer` で呼ばれる（ログイン成立前は `sandbox_jwt` Cookie が無いため）ため、この検証の対象外。ログインのレスポンスで初めて `sandbox_jwt` Cookie と（他の全レスポンス同様に）`XSRF-TOKEN` Cookie の両方が揃う

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
