
coachtech 勤怠管理アプリ (attendance-app)

ユーザーの出退勤・休憩の記録、勤怠修正申請、管理者による勤怠管理を行うWebアプリケーションです。

📋 プロジェクト概要

1. Webサービス制作 of 概要・方針

• サービス名: coachtech 勤怠管理アプリ
• サービス概要: 企業向けの独自の勤怠管理アプリケーション
• 制作の背景・目的: ユーザーの勤怠データ（出退勤・休憩）の記録と管理
• 制作目標: 初年度でのユーザー数1,000人達成
• 作業範囲: 設計、コーディング、テスト
• 納品方法: GitHubでのリポジトリ共有

2. 業務要件・サイト要件

• 現行サービスの分析: 新規サービスのため考慮しない
• 競合他社の調査・分析: 機能や画面が複雑で使いづらいという課題を踏まえ、利用しやすい画面を目指す
• ターゲットユーザー: 社会人全般
• ターゲットブラウザ・OS: PC（Chrome / Firefox / Safari 各最新バージョン）
• 画面一覧・機能要件: 新模擬案件_勤怠管理アプリ_要件シートの「画面設計」「機能要件」を参照

3. 非機能要件

• 運用・保守: クライアントが運用・保守を行う
• リリース: 開発開始から4ヶ月後を予定
• セキュリティ: アプリケーション内に限り考慮する
• SEO: 考慮しない
• コード品質: コーディング規約を参照してコーディングを行うこと。その他の要件は開発プロセスを参照。

4. システム要件

• 本番・開発環境: Laravel Sailで構築
• インフラ要件: サーバー設置・ドメイン取得・SSL化は対象外（考慮しない）
• データベース: MySQL 8.4
• 開発言語・FW: PHP 8.2 / Laravel 10.x
• バージョン管理: GitHub（開発環境はLaravel Sail / Docker）
• テスト計画: 新模擬案件_勤怠管理アプリ_要件シートの「開発プロセス」を参照

5. その他

• スケジュール: 担当コーチと相談した期間で実装すること
• プロジェクト開発体制: 開発者が1人のため作成しない
• 使用用語の定義: 特別な用語はないため作成しない

🔗 参照資料

• Figmaデザイン
https://www.figma.com/design/05TYNeIfupfwY0Hmuoivl0/%E6%A8%A1%E6%93%AC%E6%A1%88%E4%BB%B6_%E6%96%B0%E5%8B%A4%E6%80%A0%E7%AE%A1%E7%90%86%E3%82%A2%E3%83%97%E3%83%AA?node-id=0-1&t=PhQ2uJF0eDtuOMjN-1

• コーディング規約・開発プロセス (Notion)
https://estra-inc.notion.site/1263a94a2aab4e3ab81bad77db1cf186?pvs=4



🛠️ 使用技術

• サーバーサイド: PHP 8.2 / Laravel 10.x
• 認証: Laravel Fortify（認証基盤） / Laravel Sanctum
• フロントエンド: Vite 5 / Axios / Bladeテンプレート
• データベース: MySQL 8.4 / phpMyAdmin
• メール確認: Mailpit (開発用ローカルSMTPサーバー)
• 開発環境: Docker Desktop / Laravel Sail / WSL 2 (Windows環境)
💡 日本語化の方針
外部の日本語翻訳パッケージは使用していません。日本語のバリデーションメッセージは、アプリ内の FormRequest および lang/ja/ で個別に定義しています。

🏗️ 環境構築手順


必要な前提環境

• Docker Desktop（Docker Composeを含む）
• Gi
• WSL 2（Windows環境のみ必須）
※PHP、Composer、Node.js、npmなどの各種ツールは、Laravel SailのDockerコンテナ内で完結して利用できます。

1. プロジェクトの新規作成

プロジェクトを作成したい親ディレクトリ（Linux / WSLのbash）で実行し、Laravelのバージョンを10.xに固定して作成します。
bash
docker run --rm
    -u "$(id -u):$(id -g)"
    -v "$(pwd):/var/www/html" -w /var/www/html
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache
    laravelsail/php82-composer:lates
    composer create-project "laravel/laravel:^10.0" attendance-app

cd attendance-app
コードは注意してご使用ください。

2. Laravel Sail 拡張のインストール

MySQLとMailpitを追加指定して初期化します。
bash
docker run --rm
    -u "$(id -u):$(id -g)"
    -v "$(pwd):/var/www/html" -w /var/www/html
    laravelsail/php82-composer:lates
    php artisan sail:install --with=mysql,mailpi
コードは注意してご使用ください。

3. compose.yaml の編集 (phpMyAdminの追加・Mac対応)

mysql サービスと同じ sail ネットワークに phpmyadmin サービスを追記します。
yaml
  phpmyadmin:
    image: 'phpmyadmin:latest'
    ports:
      - '${FORWARD_PHPMYADMIN_PORT:-8080}:80'
    environment:
      PMA_HOST: mysql
      PMA_USER: '${DB_USERNAME}'
      PMA_PASSWORD: '${DB_PASSWORD}'
    networks:
      - sail
    depends_on:
      - mysql
コードは注意してご使用ください。
• Apple Silicon (M1〜M4) の注意点: MySQLコンテナが正常に起動しない場合は、compose.yaml の mysql サービス内に platform: 'linux/amd64' を指定してください。

4. 環境変数 (.env) の設定

.env.example を .env にコピーし、データベース名とメール設定を以下のように書き換えます（初期値の laravel から変更が必要です）。
dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=attendance_app
DB_USERNAME=sail
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=mailpi
MAIL_PORT=1025
コードは注意してご使用ください。
※Windows PowerShellでコピーを行う場合は Copy-Item .env.example .env を使用してください。

5. 提供テンプレート（Blade・CSS・JS・画像）の移行

本課題では、フロントエンド（Bladeテンプレート・CSS / JS・画像アセット）は完成品として提供されます。以下の手順で自身のプロジェクトへ移行してください。
【移行手順】
1. 自分のプロジェクトとは別の場所で、提供リポジトリをクローンします。

# 基本機能の開発時は basic ブランチを指定
git clone -b basic https://github.com/coachtech-prepared-file/Preparedblade-mockcase-Attendance.gi
→ クローンした resources/ を、自分のプロジェクトの resources/ に上書きコピー

# 応用機能（マイ勤怠レポート画面等）の着手時は advanced ブランチを指定
 自分のプロジェクトとは別の場所で:  git clone -b basic https://github.com/coachtech-prepared-file/Preparedblade-mockcase-Attendance
コードは注意してご使用ください。
2. クローンしたフォルダ内の resources/ フォルダを、自分のプロジェクトの resources/ に上書きコピーします。
3. コピーが終わったら、クローンした側のフォルダは削除して構いません。

basic    … 基本機能用（基本実装の開始時に移入）
advanced … 応用機能用（基本完了後、マイ勤怠レポート画面 等の着手時に移入）

6. Viteの設定 (vite.config.js)

画面ごとに個別のCSSを読み込むため、vite.config.js の設定内容を以下のように書き換えます。

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/sanitize.css',
                'resources/css/common.css',
                'resources/css/auth/verify-email.css',
                'resources/css/user/user-register.css',
                'resources/css/user/user-login.css',
                'resources/css/user/user-attendance-register.css',
                'resources/css/user/user-attendance-list.css',
                'resources/css/user/user-detail.css',
                'resources/css/user/user-application-list.css',
                'resources/css/admin/admin-login.css',
                'resources/css/admin/admin-attendance-list.css',
                'resources/css/admin/admin-detail.css',
                'resources/css/admin/admin-application-list.css',
                'resources/css/admin/admin-application-detail.css',
                'resources/css/admin/admin-staff-list.css',
                'resources/css/admin/admin-staff-detail.css',
                'resources/css/reports/index.css', // ⚠️ advanced（マイ勤怠レポート）ブランチのみ
                'resources/js/app.js'
            ],
            refresh: true,
            }),
    ],
});
コードは注意してご使用ください。
• resources/css/reports/index.css は、応用機能（advancedブランチ）の着手時に追加してください。

7. アプリ起動 & 初期化

bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
コードは注意してご使用ください。

🔑 開発・テスト用ログイン情報

php artisan migrate --seed を実行することで、採点・確認用の管理者および一般ユーザーアカウントが自動生成されます。

ユーザータイプ	メールアドレス	パスワード	状態 / 権限
一般ユーザー 1	user1@example.com	password	メール認証済み / テスト用ダミーデータ保持
一般ユーザー 2	user2@example.com	password	メール認証済み
一般ユーザー 3（管理者）user3@example.com	password	メール認証済み / admin_status = true (管理権限あり)


📊 テスト用ダミーデータの構成（user1の意図的データ）

シードデータ生成時、動作確認および集計ロジック検証のために、user1 に対して以下の条件を満たす過去5ヶ月＋当月分の勤怠記録（attendances）および休憩ログ（break_logs）が自動生成されます。実際の上限・近傍値に近いテストが可能です。

① 過去5ヶ月のデータ

• 平日日数: 各月平日15日間 × 5ヶ月 ＝ 計75日間 の通常勤務（9:00〜18:00）データを生成
• 固定休憩: すべてのレコードに 12:00〜13:00（1時間） の休憩を付与

② 当月（17日分）のパターンデータ

• 通常勤務: 10回（9:00〜18:00）
• 残業: 3回（9:00〜20:00 / 残業判定：1日8時間超過分）
• 遅刻: 2回（9:30〜18:00 / 始業09:00超過）
• 早退: 1回（9:00〜17:00 / 終業18:00より前の退勤）
• 長時間労働: 1回（8:00〜21:00 / 1日10時間超）

③ マイ勤怠レポート（/attendance/report）開示時の予測値

上記データが正しく生成・集計されると、user1 のレポート画面では以下の数値が算出されます。
• 過去6ヶ月 総労働時間: 744時間
• 過去6ヶ月 総残業時間: 10時間
• 過去6ヶ月 平均労働時間: 日/8時間5分
• 当月 遅刻回数: 2回
• 当月 早退回数: 1回
• 当月 長時間労働回数: 1回（1日10時間超）

⚠️ 開発・環境構築における注意点

• 外部パッケージの追加禁止: 本プロジェクトは指定の技術スタックのみで実装されています。（laravel-lang/* 等）
• 日本語化の対応: 日本語のバリデーションメッセージ等は、外部パッケージに頼らず、アプリ内の FormRequest および lang/ja/ へのメッセージ手動配置で対応しています。
• マイグレーションの適正化: マイグレーションファイルは、提出されるテーブル仕様書およびER図のテーブル表記やカーディナリティ（リレーション関係）と完全に一致するよう構成されています。
💡 開発中のTips
• フロントエンドの変更をリアルタイムに反映（ホットリロード）させたい場合は、npm run build の代わりに ./vendor/bin/sail npm run dev を実行してください。

🔑 ローカル接続URL一覧

サービス名	アクセスURL	備考
アプリケーション (TOP)	http://localhost/
一般ユーザーログイン	http://localhost/login
管理者ログイン	http://localhost/admin/login
マイ勤怠レポート	http://localhost/attendance/report
phpMyAdmin	http://localhost:8080/	データベース管理ツール
Mailpit	http://localhost:8025/	受信メール確認用ツール

※ポート番号を変更した場合は、localhost の後ろを各自設定したポートに書き換えてください。

📐 命名に関する設計判断

命名に関する設計判断 画面設計書（PG:6、PG:12、PG:13）等では「打刻の修正申請」と記述されているため、初期は stamp_correction_request で基本設計書のパスに示し、実装に取り組んでいました。しかし、PM確認の結果、モデル・テーブル・ルート間の一貫性と実装・保守の容易性を最優先し、最終的に Attendance / Correction の命名を維持する方針としています。

## ER
erDiagram
    users ||--o{ attendances : records
    attendances ||--o{ break_logs : contains
    users ||--o{ attendance_corrections : submits
    attendances ||--o{ attendance_corrections : receives

    users {
        bigint id PK
        string name
        string email UK
        boolean is_admin
        timestamp email_verified_a
        string password
        text two_factor_secre
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_a
        string role
        string attendance_status
        string remember_token
        timestamp created_a
        timestamp updated_a
    }

    attendances {
        bigint id PK
        bigint user_id FK
        date date
        time clock_in
        time clock_ou
        text commen
        timestamp created_a
        timestamp updated_a
    }

    break_logs {
        bigint id PK
        bigint attendance_id FK
        time break_in
        time break_ou
        timestamp created_a
        timestamp updated_a
    }

    attendance_corrections {
        bigint id PK
        bigint attendance_id FK
        bigint user_id FK
        date original_date
        time new_clock_in
        time new_clock_ou
        integer status
        timestamp approved_a
        json new_breaks
        text commen
        timestamp created_a
        timestamp updated_a
    }
コードは注意してご使用ください。

🧪 テスト・品質管理・検証コマンド


自動テストの実行

bash
docker compose exec laravel.test php artisan tes
コードは注意してご使用ください。

コード整形・コード品質チェック (Laravel Pint)

bash
docker compose exec laravel.test php ./vendor/bin/pint --test app artisan bootstrap/app.php config database lang public/index.php routes tests
コードは注意してご使用ください。

コンテナの停止（データの保持）

bash
docker compose down
コードは注意してご使用ください。
• 💡 コンテナは停止・削除されますが、データベース内のデータ（MySQLボリューム）は破棄されず安全に保持されます。

👤 作成者
山村龍世
