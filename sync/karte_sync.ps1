# ============================================================
#  カルテ DB同期スクリプト（方法①・③共用）
#  使い方：
#    対話モード   → ダブルクリック または PowerShell から実行
#    自動ダウンロード → karte_sync.ps1 -mode auto
# ============================================================
param(
    [string]$mode = "menu"   # "menu" | "auto"
)

$LOCAL_API  = "http://localhost/karte/api/sync.php"
$REMOTE_API = "https://opened.sakura.ne.jp/karte/api/sync.php"
$TOKEN      = "karte_sync_opened_2026_mKGG"
$LOG_FILE   = "$PSScriptRoot\sync_log.txt"

function Write-Log($msg) {
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$ts] $msg"
    Write-Host $line
    Add-Content -Path $LOG_FILE -Value $line -Encoding UTF8
}

function Get-Status($apiUrl, $label) {
    try {
        $res = Invoke-RestMethod -Uri "$apiUrl`?action=status&token=$TOKEN" -TimeoutSec 10
        Write-Host "`n【$label】" -ForegroundColor Cyan
        Write-Host "  環境    : $($res.env)"
        Write-Host "  最終更新 : $($res.last_updated)"
        foreach ($kv in $res.counts.PSObject.Properties) {
            Write-Host ("  {0,-20}: {1} 件" -f $kv.Name, $kv.Value)
        }
    } catch {
        Write-Host "【$label】接続失敗: $_" -ForegroundColor Red
    }
}

function Sync-DB($srcApi, $dstApi, $srcLabel, $dstLabel) {
    Write-Log "同期開始：$srcLabel → $dstLabel"

    # エクスポート
    Write-Host "`n[$srcLabel] エクスポート中..." -ForegroundColor Yellow
    try {
        $expData = Invoke-RestMethod -Uri "$srcApi`?action=export&token=$TOKEN" -TimeoutSec 60
    } catch {
        Write-Log "ERROR エクスポート失敗: $_"
        Write-Host "エクスポート失敗: $_" -ForegroundColor Red
        return $false
    }
    $totalRows = 0
    foreach ($kv in $expData.tables.PSObject.Properties) { $totalRows += $kv.Value.Count }
    Write-Log "エクスポート完了: $totalRows 件"
    Write-Host "  取得完了: $totalRows 件" -ForegroundColor Green

    # インポート
    Write-Host "[$dstLabel] インポート中..." -ForegroundColor Yellow
    try {
        $body = $expData | ConvertTo-Json -Depth 20 -Compress
        $impData = Invoke-RestMethod -Uri "$dstApi`?action=import&token=$TOKEN" `
                    -Method POST -Body $body -ContentType "application/json; charset=utf-8" -TimeoutSec 120
    } catch {
        Write-Log "ERROR インポート失敗: $_"
        Write-Host "インポート失敗: $_" -ForegroundColor Red
        return $false
    }

    if (-not $impData.success) {
        Write-Log "ERROR: $($impData.error)"
        Write-Host "エラー: $($impData.error)" -ForegroundColor Red
        return $false
    }

    Write-Log "同期完了: $srcLabel → $dstLabel"
    Write-Host "`n✅ 同期完了！" -ForegroundColor Green
    Write-Host "  内訳:" -ForegroundColor Green
    foreach ($kv in $impData.imported.PSObject.Properties) {
        Write-Host ("    {0,-20}: {1} 件" -f $kv.Name, $kv.Value) -ForegroundColor Green
    }
    return $true
}

# ── 自動モード（タスクスケジューラから実行） ──────────────
if ($mode -eq "auto") {
    Write-Log "=== 自動同期開始（ログオン時） ==="
    $ok = Sync-DB -srcApi $REMOTE_API -dstApi $LOCAL_API -srcLabel "サーバー" -dstLabel "ローカル"
    if ($ok) {
        # 通知バルーンを表示（Windows 10/11）
        [void][System.Reflection.Assembly]::LoadWithPartialName("System.Windows.Forms")
        $notify = New-Object System.Windows.Forms.NotifyIcon
        $notify.Icon = [System.Drawing.SystemIcons]::Information
        $notify.BalloonTipTitle = "カルテ 自動同期"
        $notify.BalloonTipText  = "サーバーのデータをローカルに同期しました。"
        $notify.Visible = $true
        $notify.ShowBalloonTip(4000)
        Start-Sleep 5
        $notify.Dispose()
    }
    Write-Log "=== 自動同期終了 ==="
    exit
}

# ── 対話メニュー ─────────────────────────────────────────
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$Host.UI.RawUI.WindowTitle = "カルテ DB同期ツール"

while ($true) {
    Clear-Host
    Write-Host "======================================" -ForegroundColor Cyan
    Write-Host "  カルテ データベース同期ツール"       -ForegroundColor Cyan
    Write-Host "======================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  1. ステータス確認（件数・最終更新）"
    Write-Host "  2. ⬇️  ダウンロード（サーバー → ローカル）"
    Write-Host "  3. ⬆️  アップロード（ローカル → サーバー）"
    Write-Host "  4. 終了"
    Write-Host ""
    $choice = Read-Host "番号を入力してください"

    switch ($choice) {
        "1" {
            Get-Status -apiUrl $LOCAL_API  -label "ローカル（家のPC）"
            Get-Status -apiUrl $REMOTE_API -label "サーバー（本番）"
            Write-Host ""
            Read-Host "Enterで戻る"
        }
        "2" {
            Write-Host ""
            Write-Host "⚠️  ローカルのデータがサーバーのデータで上書きされます。" -ForegroundColor Yellow
            $confirm = Read-Host "続けますか？ (y/N)"
            if ($confirm -match "^[yY]$") {
                Sync-DB -srcApi $REMOTE_API -dstApi $LOCAL_API -srcLabel "サーバー" -dstLabel "ローカル"
            } else {
                Write-Host "キャンセルしました。" -ForegroundColor Gray
            }
            Write-Host ""
            Read-Host "Enterで戻る"
        }
        "3" {
            Write-Host ""
            Write-Host "⚠️  サーバーのデータがローカルのデータで上書きされます。" -ForegroundColor Yellow
            $confirm = Read-Host "続けますか？ (y/N)"
            if ($confirm -match "^[yY]$") {
                Sync-DB -srcApi $LOCAL_API -dstApi $REMOTE_API -srcLabel "ローカル" -dstLabel "サーバー"
            } else {
                Write-Host "キャンセルしました。" -ForegroundColor Gray
            }
            Write-Host ""
            Read-Host "Enterで戻る"
        }
        "4" { exit }
        default {
            Write-Host "1〜4を入力してください。" -ForegroundColor Red
            Start-Sleep 1
        }
    }
}
