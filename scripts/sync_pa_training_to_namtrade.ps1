param(
    [string]$Remote = 'root@150.95.113.192',
    [string]$RemoteProject = '/var/www/namtrade.click'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$php = Get-Command php -ErrorAction Stop
$ssh = Get-Command ssh -ErrorAction Stop
$scp = Get-Command scp -ErrorAction Stop
$exportScript = Join-Path $PSScriptRoot 'export_pa_training_sync.php'
$bundlePath = Join-Path ([System.IO.Path]::GetTempPath()) ('namtrade-pa-sync-' + [guid]::NewGuid().ToString('N') + '.json')
$remoteBundle = '/tmp/namtrade-pa-sync-' + [guid]::NewGuid().ToString('N') + '.json'
$uploaded = $false

try {
    Push-Location $projectRoot
    try {
        & $php.Source $exportScript $bundlePath
        if ($LASTEXITCODE -ne 0) { throw 'Could not export local PA training data.' }
    } finally {
        Pop-Location
    }

    $bundle = Get-Item -LiteralPath $bundlePath
    if ($bundle.Length -gt 100MB) { throw 'PA sync bundle exceeds the 100 MB safety limit.' }

    & $scp.Source -q $bundlePath ("${Remote}:$remoteBundle")
    if ($LASTEXITCODE -ne 0) { throw 'Could not securely upload the PA sync bundle.' }
    $uploaded = $true

    $remoteImporter = "$RemoteProject/scripts/import_pa_training_sync.php"
    $backupRoot = '/var/backups/namtrade-pa-training'
    $remoteCommand = "chmod 644 '$remoteBundle' && install -d -o www-data -g www-data -m 750 '$backupRoot' && sudo -u www-data env PA_TRAINING_SYNC_BACKUP_DIR='$backupRoot' php '$remoteImporter' '$remoteBundle'"
    & $ssh.Source -o BatchMode=yes -o ConnectTimeout=15 $Remote $remoteCommand
    if ($LASTEXITCODE -ne 0) { throw 'Namtrade rejected the PA sync bundle; existing data was left unchanged.' }
} finally {
    Remove-Item -LiteralPath $bundlePath -Force -ErrorAction SilentlyContinue
    if ($uploaded) {
        & $ssh.Source -o BatchMode=yes -o ConnectTimeout=15 $Remote "rm -f -- '$remoteBundle'" *> $null
    }
}
