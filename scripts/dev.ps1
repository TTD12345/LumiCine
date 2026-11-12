$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source }

if (-not $phpPath) {
    $wingetPackages = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path $wingetPackages) {
        $phpPackage = Get-ChildItem -LiteralPath $wingetPackages -Directory -Filter 'PHP.PHP.*' |
            Sort-Object Name -Descending |
            Where-Object { Test-Path (Join-Path $_.FullName 'php.exe') } |
            Select-Object -First 1

        if ($phpPackage) {
            $phpPath = Join-Path $phpPackage.FullName 'php.exe'
        }
    }
}

if (-not $phpPath) {
    Write-Error 'PHP CLI was not found. Install PHP 8.4 with winget install --id PHP.PHP.8.4 --exact, then run npm run dev again.'
    exit 1
}

& $phpPath -S 127.0.0.1:8000
exit $LASTEXITCODE
