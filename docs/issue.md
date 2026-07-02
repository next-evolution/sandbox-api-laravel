# Known Issues / 設計メモ

---

# DB_HOST は localhost ではなく 127.0.0.1

**ステータス: 解決済み（.env.example を 127.0.0.1 に修正済み）**

## 事象

`DB_HOST=localhost` を使うと MySQL への接続が失敗する。

## 原因

PHP が `localhost` を Unix ドメインソケット接続として解釈するため、Docker のポートフォワード（TCP）経由で接続できない。

## 解決策

`.env` / `.env.example` で `DB_HOST=127.0.0.1` を使う。

---

# tymon/jwt-auth パッケージとの競合

**ステータス: 解決済み（tymon/jwt-auth を削除済み）**

## 事象

`POST /api/v1/auth/login` で以下のエラーが発生。

```
"message": "Secret is not set.",
"exception": "Tymon\\JWTAuth\\Exceptions\\JWTException"
```

## 原因

`tymon/jwt-auth` が Laravel のサービスプロバイダー自動検出により読み込まれ、`JwtAuthMiddleware` よりも先に動作していた。

## 解決策

`composer remove tymon/jwt-auth` で削除。  
このプロジェクトでは JWT 検証に `firebase/php-jwt` を直接使う。

---

# AuthUser Redis セッション設計

## 現状設計

`JwtAuthMiddleware` が Redis から `AuthUser`（admin・approved フラグ含む）を取得する。

- **セッションあり**: Redis の `AuthUser` を使い TTL をリセット
- **セッションなし**: `sandbox_user` テーブルから `AuthUser` を復元して Redis に保存（silent login）
- **DB にも存在しない**: 403 FORBIDDEN を返す

## Silent Login を採用した理由

JWT の RS256 検証が通っている時点でユーザーは認証済み。Redis の TTL 切れはキャッシュの欠如であり、再ログインを強制する根拠にならない。

## approved 変更の即時反映

`approved=false` に変更した際は `RedisSessionRepository::deleteBySub(sub)` を呼ぶ。  
次のリクエストで silent login → `approved=false` → 403 となる。

---

# isApproved() メソッドの追加

**ステータス: 解決済み**

## 事象

`GET /api/v1/user` で以下のエラーが発生。

```
Call to undefined method App\Domain\Models\User::isApproved()
```

## 原因

`User` ドメインモデルに `isApproved()` メソッドが未実装だった。

## 解決策

`app/Domain/Models/User.php` に `isApproved(): bool` を追加。

---

# JWT audit クレームの扱い

## 現状設計

`JwtService::parse()` では `aud`（audience）を配列として処理する。

```php
$audiences = (array) ($decoded->aud ?? []);
```

Cognito の JWT は `aud` が文字列単体の場合と配列の場合があるため、`(array)` キャストで統一。  
設定された `JWT_AUDIENCE1/2/3` のいずれかと一致すれば検証通過。

---

# 要検討

## Eloquent vs Query Builder の使い分け

現状は Eloquent の `where()->first()` / `where()->update()` を使用。  
パフォーマンスが問題になる場合は `DB::table()` の Query Builder に切り替えを検討。

## バーデータの複数テーブル構成

SpringBoot と同様に `fx_bar_m15`, `fx_bar_h1`, `fx_bar_h4`, `fx_bar_d1` の 4 テーブルに分けるか、  
単一テーブル + `bar_type` カラムで管理するかは Phase 5 実装時に決定。
