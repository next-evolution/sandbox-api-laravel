# docs-check

`docs/api.md` と `docs/architecture.md` が実装と乖離していないかチェックする。

## 手順

以下を順番に実施し、差異をまとめてレポートする。

### 1. コントローラー vs `docs/api.md`

- `app/Http/Controllers/` 配下の全コントローラーを読む
- 各エンドポイント（HTTPメソッド・パス）を `routes/api.php` と合わせて抽出し、`docs/api.md` の記載と照合する
- レスポンス形式（`returnCode` でラップされているか、直接返却か）を確認し、`docs/api.md` の共通仕様と照合する

### 2. ミドルウェア vs `docs/api.md`

- `routes/api.php` を読む
- 各ルートに適用されているミドルウェア（`jwt.auth`、`role.admin`、なし）を確認し、`docs/api.md` の認証欄と照合する

### 3. 認証フロー vs `docs/architecture.md`

- `app/Http/Middleware/JwtAuthMiddleware.php` を読む
- `app/Services/JwtService.php` を読む
- 実際のフロー（JWT 検証 → Redis → silent login → approved チェック）と `docs/architecture.md` の「認証フロー」セクションを照合する

### 4. `AuthUser` vs `docs/architecture.md`

- `app/Domain/Models/AuthUser.php` を読む
- フィールド・メソッド・`toArray()` / `fromArray()` の実装と `docs/architecture.md` の「AuthUser」セクションを照合する

### 5. ディレクトリ構成 vs `docs/architecture.md`

- `app/` 配下の実際のディレクトリ構造を確認する
- `docs/architecture.md` の「ディレクトリ構成」テーブルと照合する

### 6. 例外ハンドリング vs `docs/architecture.md`

- `bootstrap/app.php` の `withExceptions()` を読む
- `app/Domain/Exceptions/` 配下の例外クラスを確認する
- 例外クラスと HTTP ステータスのマッピングが `docs/architecture.md` の「例外ハンドリング」テーブルと一致するか照合する

### 7. 実装規約 vs `CLAUDE.md`

以下の規約が守られているか、全コントローラー・ユースケース・ドメインモデルを確認する。

- `declare(strict_types=1)` が全 PHP ファイルに記述されているか
- `base64_decode()` が `strict: true` 付きで呼ばれているか
- `userId` / `email` をリクエストボディから取得していないか（`$request->attributes->get('authUser')` から取得しているか）
- Carbon の `toIso8601String()` で日時を返しているか（`format()` で直接フォーマットしていないか）

## レポート形式

差異がある場合:

```
## 差異あり

### [ファイル名]
- **項目**: （ドキュメントの記載）
- **実装**: （実際の実装）
- **修正案**: （推奨される修正内容）
```

差異がない場合:

```
## 差異なし
docs/*.md と実装の間に乖離は見つかりませんでした。
```
