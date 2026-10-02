# API エンドポイント一覧

ベースパス: `/api`（Laravel の api ルートプレフィックス）+ `/v1/...`  
例: `POST /api/v1/auth/login/web`

---

## 共通仕様

### レスポンス形式

多くのエンドポイントで共通の JSON 構造でラップされる（例外は下表参照）。

```json
// 単件・操作系
{ "returnCode": 0, "message": null, "user": { ... } }

// 一覧検索系
{ "returnCode": 0, "totalCount": 100, "searchCount": 100, "totalPage": 5, "list": [ ... ] }
```

`returnCode` は整数値: `0`=Ok（正常）/ `1`=Warn（警告）/ `2`=Error（エラー）/ `2147483647`=Fatal

**ラップされないエンドポイント:**

| エンドポイント | 戻り型 |
|---|---|
| `GET /v1/fx/master-list/*` | 配列 |
| `GET /v1/fx/symbol/currency-pair-list` | 配列 |
| `GET /v1/fx/symbol/currency-index-list` | 配列 |
| `GET /v1/fx/symbol/{symbol}` | オブジェクト（直接） |
| `POST /v1/fx/symbol` | ボディなし（200 OK） |
| `PUT /v1/fx/symbol/{symbol}` | ボディなし（200 OK） |
| `GET /v1/fx/country/{code}` | オブジェクト（直接） |
| `POST /v1/fx/country` | ボディなし（200 OK） |
| `PUT /v1/fx/country/{code}` | ボディなし（200 OK） |
| `GET /v1/fx/summer-time/{targetYear}` | オブジェクト（直接） |
| `POST /v1/fx/summer-time` | ボディなし（200 OK） |
| `PUT /v1/fx/summer-time/{targetYear}` | ボディなし（200 OK） |
| `GET /v1/fx/economic-indicator/{countryCode}/{code}` | オブジェクト（直接） |
| `POST /v1/fx/economic-indicator` | ボディなし（200 OK） |
| `PUT /v1/fx/economic-indicator/{countryCode}/{code}` | ボディなし（200 OK） |
| `GET /v1/fx/bar-data/{symbolType}/{barType}` | 配列 |
| `POST /v1/fx/bar-data/import-csv/{symbol}/{barType}/{skipLatest}` | オブジェクト |
| `GET /v1/fx/economic-indicator-data/{countryCode}/{code}/{publication}` | オブジェクト（直接） |
| `POST /v1/fx/economic-indicator-data` | ボディなし（200 OK） |
| `PUT /v1/fx/economic-indicator-data/{countryCode}/{code}/{publication}` | ボディなし（200 OK） |
| `POST /v1/fx/economic-indicator-data/import-text` | 配列 |

### 認証

- 全エンドポイントで JWT が必要。トークン受け渡し方式はクライアント種別で異なる（詳細は `documents/architecture/auth.md`「トークン受け渡し方式（クライアント種別）」参照）
  - sandbox-app-flutter: `Authorization: Bearer <token>` ヘッダー
  - sandbox-spa-react（Web）: `sandbox_jwt` という名前の HttpOnly Cookie（ログイン成功時に発行）。JWT の値は Bearer の場合と同一
  - `resolveToken()` は `Authorization` ヘッダーを優先し、無ければ Cookie を見る
- `/v1/fx/master-list/**` のみ認証不要（ミドルウェアなし）
- ログインAPI（`/v1/auth/login/web`・`/v1/auth/login/app`）と `POST`/`GET /v1/user` の3エンドポイントは JWT は必須だが「承認済み（approved）」は不要（`jwt.auth:any` ミドルウェア）。未承認・未登録でも到達させ、コントローラ/UseCase側で個別に判定する
- それ以外のエンドポイントは `jwt.auth`（デフォルト）ミドルウェアで承認済みユーザーのみに制限
- 管理者専用エンドポイントは `role.admin` ミドルウェアで制御（非管理者は 403）
- CORS: React（別オリジン配信）からの Cookie 送受信のため `CORS_ORIGIN1`/`CORS_ORIGIN2`（`config/cors.php`）を許可オリジンとして明示し、`supports_credentials: true` を設定
- CSRF: Cookie方式（Web）の状態変更リクエスト（GET/HEAD/OPTIONS以外）はダブルサブミットCookie（`XSRF-TOKEN` Cookie ⇔ `X-XSRF-TOKEN` ヘッダー）で検証する（`CsrfCookieMiddleware`）。Bearer方式（Flutter）は検証をスキップする。ログインAPI自体は常に Bearer リクエストとして呼ばれるため、この検証の対象外

---

## Auth

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/auth/login/web` | sandbox-spa-react（Web）向けログイン。JWT の email と Base64 デコードしたリクエストの email を照合し、AuthUser を Redis に保存。成功時は JWT を `sandbox_jwt` Cookie として `Set-Cookie`（レスポンスボディにトークンは含めない） |
| POST | `/v1/auth/login/app` | sandbox-app-flutter向けログイン。処理内容は `/login/web` と同じ（Cookie発行は行わない） |
| POST | `/v1/auth/logout-api` | ログアウト。Redis セッションを削除。`sandbox_jwt` Cookie も失効させる（Flutter は Cookie 未使用のため無害） |

---

## User

| メソッド | パス | 説明 |
|---|---|---|
| GET | `/v1/user` | プロフィール取得。未承認の場合は `returnCode: 1`（Warn） |
| POST | `/v1/user` | ユーザー登録。`sub`・`email` は JWT から取得 |
| PUT | `/v1/user/{userId}` | ユーザー情報更新。`{userId}` は Base64 エンコード済み。他ユーザーは 403 |

---

## Admin（管理者専用 — 非管理者は 403）

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/admin/users` | ユーザー検索（`emailAddress`・`approved`・ページング） |
| PUT | `/v1/admin/users/approved/{userId}` | ユーザー承認 |
| PUT | `/v1/admin/users/block/{userId}` | ユーザーブロック / 解除 |
| PUT | `/v1/admin/users/admin/{userId}` | 管理者権限の付与 / 剥奪 |
| GET | `/v1/admin/master-refresh` | Redis マスターキャッシュのステータス取得 |
| PUT | `/v1/admin/master-refresh` | Redis マスターキャッシュをリフレッシュ |

---

## FX - Master List（公開 — 認証不要）

| メソッド | パス | 説明 |
|---|---|---|
| GET | `/v1/fx/master-list/symbol/{symbolType}` | シンボル一覧（`symbolType` でフィルタ） |
| GET | `/v1/fx/master-list/country` | 国一覧 |
| GET | `/v1/fx/master-list/currency-pair` | 通貨ペア一覧 |
| GET | `/v1/fx/master-list/currency-index` | 通貨インデックス一覧 |
| GET | `/v1/fx/master-list/economic-indicator/{countryCode}` | 経済指標一覧（国コードでフィルタ） |

---

## FX - Symbol

| メソッド | パス | 説明 |
|---|---|---|
| GET | `/v1/fx/symbol/currency-pair-list` | 通貨ペア一覧（最大500件） |
| GET | `/v1/fx/symbol/currency-index-list` | 通貨インデックス一覧（最大500件） |
| POST | `/v1/fx/symbol/search` | シンボル検索（`symbolType`・ページング） |
| POST | `/v1/fx/symbol` | シンボル追加 |
| GET | `/v1/fx/symbol/{symbol}` | シンボル取得 |
| PUT | `/v1/fx/symbol/{symbol}` | シンボル更新 |

---

## FX - Country

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/country/search` | 国検索（ページング） |
| POST | `/v1/fx/country` | 国追加 |
| GET | `/v1/fx/country/{code}` | 国取得 |
| PUT | `/v1/fx/country/{code}` | 国更新 |

---

## FX - Summer Time

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/summer-time/search` | サマータイム検索（ページング） |
| POST | `/v1/fx/summer-time` | サマータイム追加 |
| GET | `/v1/fx/summer-time/{targetYear}` | サマータイム取得 |
| PUT | `/v1/fx/summer-time/{targetYear}` | サマータイム更新 |

---

## FX - Economic Indicator

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/economic-indicator/search` | 経済指標検索（`countryCode`・`importance`・`name`・ページング） |
| POST | `/v1/fx/economic-indicator` | 経済指標追加 |
| GET | `/v1/fx/economic-indicator/{countryCode}/{code}` | 経済指標取得 |
| PUT | `/v1/fx/economic-indicator/{countryCode}/{code}` | 経済指標更新 |

---

## FX - Economic Indicator Data

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/economic-indicator-data/search` | 経済指標データ検索（`code`・`importance`・`countryCode`・`publicationBaseDate`・ページング） |
| POST | `/v1/fx/economic-indicator-data` | 経済指標データ追加 |
| GET | `/v1/fx/economic-indicator-data/{countryCode}/{code}/{publication}` | 経済指標データ取得（`publication` は `yyyy-MM-dd HH:mm:ss`） |
| PUT | `/v1/fx/economic-indicator-data/{countryCode}/{code}/{publication}` | 経済指標データ更新 |
| POST | `/v1/fx/economic-indicator-data/import-text` | テキストファイル一括インポート（`multipart/form-data`、`uploadFileList`） |

---

## FX - Bar Data

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/bar-data` | バーデータ検索（`symbol`・`barType`・日付範囲・ページング） |
| POST | `/v1/fx/bar-data/import-csv/{symbol}/{barType}/{skipLatest}` | CSV インポート（`multipart/form-data`、`uploadFile`） |
| GET | `/v1/fx/bar-data/{symbolType}/{barType}` | インポートステータス取得 |

---

## FX - ZigZag（未実装）

sandbox-api-springboot には存在するが、本プロジェクトには未実装（将来的に追加実装予定）。詳細は [docs/issue.md](issue.md) 参照。

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/zigzag` | ZigZag 検索（`barType`・`symbol`・`depth`・各種フィルタ・ページング） |
| POST | `/v1/fx/zigzag/status` | ZigZag ステータス取得（`symbolType`・`barType`・`depth`） |
| POST | `/v1/fx/zigzag/generate` | ZigZag 生成（`symbol`・`barType`・`depth`・`barDateTime`・`loadSize`） |
| POST | `/v1/fx/zigzag/bar-data` | ZigZag バーデータ取得（`barType`・`symbol`・`depth`・`waveStart`・`wave`） |

---

## FX - Trade Simulation（未実装）

sandbox-api-springboot には存在するが、本プロジェクトには未実装（将来的に追加実装予定）。詳細は [docs/issue.md](issue.md) 参照。

| メソッド | パス | 説明 |
|---|---|---|
| POST | `/v1/fx/trade/simulation` | トレードシミュレーション（`riskAmount`・`firstLotRatio`・`entry`・`positionList`） |
