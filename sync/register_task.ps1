# ============================================================
#  方法③：ログオン時自動同期 — タスクスケジューラ登録スクリプト
#  管理者権限で実行してください
# ============================================================
$taskName   = "カルテ自動同期"
$scriptPath = "$PSScriptRoot\karte_sync.ps1"

# 既存タスクを削除
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

$action  = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`" -mode auto"

# トリガー：ログオン時 + 起動後5分後（XAMPPの起動を待つ）
$trigger1 = New-ScheduledTaskTrigger -AtLogOn
$trigger2 = New-ScheduledTaskTrigger -AtStartup
$trigger2.Delay = "PT5M"   # 5分後

$settings = New-ScheduledTaskSettingsSet `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5) `
    -RunOnlyIfNetworkAvailable `
    -StartWhenAvailable

$principal = New-ScheduledTaskPrincipal `
    -UserId $env:USERNAME `
    -LogonType Interactive `
    -RunLevel Limited

Register-ScheduledTask `
    -TaskName  $taskName `
    -Action    $action `
    -Trigger   @($trigger1, $trigger2) `
    -Settings  $settings `
    -Principal $principal `
    -Description "カルテアプリのDBをサーバーからローカルに自動同期します（ログオン時）"

Write-Host "✅ タスク「$taskName」を登録しました。" -ForegroundColor Green
Write-Host "   次回ログオン時から自動同期が実行されます。" -ForegroundColor Green
Write-Host ""
Write-Host "確認方法：タスクスケジューラ → タスクスケジューラライブラリ → '$taskName'" -ForegroundColor Cyan
Read-Host "Enterで終了"
