# sandbox-api-laravel

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-2-885630?logo=composer&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-8.0-DC382D?logo=redis&logoColor=white)
![AWS Cognito](https://img.shields.io/badge/AWS_Cognito-FF9900?logo=amazonaws&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?logo=docker&logoColor=white)

FX トレード支援を目的としたバックエンド REST API。  
`sandbox-api-springboot` と同等の機能を PHP 8.3 / Laravel 11 で実装。  
認証は AWS Cognito（RS256 JWT）、セッション管理は Redis で実装。

---

## アーキテクチャ

DDD（ドメイン駆動設計）に基づくレイヤー構成。SpringBoot のマルチモジュールを単一アプリの `app/` ディレクトリ構造で表現。

### ディレクトリ構成

| ディレクトリ | 役割 |
|---|---|
| `app/Domain/` | ドメインモデル、リポジトリインターフェース、ドメイン例外。フレームワーク非依存。 |
| `app/UseCases/` | ユースケース。`Domain` のみに依存。各ユースケースは `execute()` メソッドを持つ。 |
| `app/Infrastructure/` | リポジトリ実装（Eloquent）、Redis 実装。 |
| `app/Services/` | JWT 検証など横断的サービス。 |
| `app/Http/Controllers/` | REST コントローラー。 |
| `app/Http/Middleware/` | JWT 認証・管理者チェックミドルウェア。 |

詳細は [docs/architecture.md](./docs/architecture.md) を参照。

---

## 主な機能

| ドメイン | 主なエンドポイント |
|---|---|
| **認証 / Auth** | ログイン・ログアウト（AWS Cognito JWT + Redis セッション） |
| **ユーザー / User** | プロフィール取得・ユーザー登録・情報更新 |
| **管理者 / Admin** | ユーザー検索・承認・ブロック・管理者権限付与、Redis キャッシュ管理 |
| **FX マスター** | 通貨シンボル・国・通貨ペア・経済指標（公開 API） |
| **FX バーデータ** | OHLC バーデータ検索・CSV 一括インポート |
| **ZigZag 分析** | ZigZag 生成・検索・ステータス取得・バーデータ取得 |
| **トレードシミュレーション** | リスク額・ロット比率・エントリーに基づくシミュレーション |

エンドポイント詳細は [docs/api.md](./docs/api.md) を参照。

---

## Getting Started

### 1. ローカルインフラ起動

MySQL・Redis は `sandbox-tools` リポジトリで管理。

```bash
# sandbox-tools/docker/ で実行
cd ../sandbox-tools/docker
cp .env.compose.example .env.compose  # 初回のみ
docker compose --env-file .env.compose up -d
```

> `initdb.d/` スクリプトが初回起動時に DB 作成・アプリユーザー作成・管理者ユーザー INSERT を自動実行します。  
> 再初期化する場合は `docker compose down -v` でボリュームを削除してから再起動してください。

### 2. アプリケーション環境変数

```bash
# テンプレートをコピーして実際の値を設定（初回のみ）
cp .env.example .env
```

| 変数名 | 説明 | 例 |
|---|---|---|
| `DB_HOST` | MySQL ホスト（`127.0.0.1` を使用） | `127.0.0.1` |
| `DB_PORT` | MySQL ポート | `43306` |
| `DB_DATABASE` | データベース名 | `sandbox1` |
| `DB_USERNAME` | DB ユーザー | `sandbox_app` |
| `DB_PASSWORD` | DB パスワード | — |
| `REDIS_HOST` | Redis ホスト | `127.0.0.1` |
| `REDIS_PORT` | Redis ポート | `46379` |
| `JWT_ISSUER` | Cognito Issuer URL | `https://cognito-idp.ap-northeast-1.amazonaws.com/...` |
| `JWT_AUDIENCE1/2/3` | Cognito App Client ID | — |
| `CORS_ORIGIN1/2` | 許可オリジン | `http://localhost` |
| `BUCKET_NAME` | S3 バケット名（またはローカルパス） | `../local/storage` |

### 3. セットアップ & 起動

```bash
# 依存関係インストール
composer install

# アプリケーションキー生成
php artisan key:generate

# DB マイグレーション
php artisan migrate

# サーバー起動（ポート 8080）
php artisan serve --port=8080
```

---

## Claude Code カスタムコマンド

| コマンド | 用途 |
|---|---|
| `/docs-check` | `docs/*.md` と実装の乖離チェック。コミット前などに手動実行する |

---

## 開発 Tips

VS Code 設定・PHP 開発 Tips: [docs/tips.md](./docs/tips.md)
