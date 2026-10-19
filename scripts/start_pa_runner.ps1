param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('local', 'vps')]
    [string]$HostId,
    [string]$Symbols = '',
    [string]$ServerUrl = 'https://namtrade.click/pa_runner_api.php',
    [string]$TerminalPath = '',
    [string]$PythonExe = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runner = Join-Path $PSScriptRoot 'pa_mt5_runner.py'

if ([string]::IsNullOrWhiteSpace($Symbols)) {
    $Symbols = Read-Host 'MT5 symbols, comma separated'
}
if ([string]::IsNullOrWhiteSpace($Symbols)) {
    throw 'At least one MT5 symbol is required.'
}
if ([string]::IsNullOrWhiteSpace($env:PA_RUNNER_TOKEN)) {
    $secure = Read-Host 'PA_RUNNER_TOKEN from the Namtrade server' -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try {
        $env:PA_RUNNER_TOKEN = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

if ([string]::IsNullOrWhiteSpace($PythonExe)) {
    $candidates = @(
        (Join-Path $projectRoot '.venv\Scripts\python.exe'),
        'C:\Python314\python.exe',
        'C:\Python313\python.exe',
        'C:\Python312\python.exe',
        'C:\Python311\python.exe'
    )
    if (-not [string]::IsNullOrWhiteSpace($env:AI_TRADE_PYTHON)) {
        $candidates = @($env:AI_TRADE_PYTHON) + $candidates
    }
    if (-not [string]::IsNullOrWhiteSpace($env:LOCALAPPDATA)) {
        foreach ($version in @('Python314', 'Python313', 'Python312', 'Python311')) {
            $candidates += Join-Path $env:LOCALAPPDATA "Programs\Python\$version\python.exe"
        }
    }
    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            $PythonExe = $candidate
            break
        }
    }
}
if ([string]::IsNullOrWhiteSpace($PythonExe)) {
    $command = Get-Command python -ErrorAction SilentlyContinue
    if ($null -eq $command) {
        throw 'Python 64-bit was not found. Pass -PythonExe with the path to python.exe.'
    }
    $PythonExe = $command.Source
}

$arguments = @($runner, '--host', $HostId, '--url', $ServerUrl, '--symbols', $Symbols)
if (-not [string]::IsNullOrWhiteSpace($TerminalPath)) {
    $arguments += @('--terminal', $TerminalPath)
}
Write-Host "Starting $HostId MT5 runner. Press Ctrl+C to release this host."
& $PythonExe @arguments
exit $LASTEXITCODE
