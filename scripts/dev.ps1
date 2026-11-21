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
    $directory = Get-Item -LiteralPath $PSScriptRoot
    while ($directory -and -not $phpPath) {
        $laragonPhp = Join-Path $directory.FullName 'bin\php'
        if (Test-Path -LiteralPath $laragonPhp) {
            $phpPackage = Get-ChildItem -LiteralPath $laragonPhp -Directory |
                Sort-Object Name -Descending |
                Where-Object { Test-Path (Join-Path $_.FullName 'php.exe') } |
                Select-Object -First 1

            if ($phpPackage) {
                $phpPath = Join-Path $phpPackage.FullName 'php.exe'
            }
        }

        $directory = $directory.Parent
    }
}

$serverArguments = @()
$loadedExtensions = & $phpPath -m
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

if ($loadedExtensions -notcontains 'pdo_mysql') {
    $extensionDirectory = Join-Path (Split-Path $phpPath) 'ext'
    $pdoMysqlExtension = Join-Path $extensionDirectory 'php_pdo_mysql.dll'
    if (-not (Test-Path -LiteralPath $pdoMysqlExtension)) {
        Write-Error "PDO_MYSQL is required to use the database, but php_pdo_mysql.dll was not found under $extensionDirectory."
        exit 1
    }

    $serverArguments += @('-d', "extension=$pdoMysqlExtension")
}

$serverArguments += @('-S', '127.0.0.1:8000')
& $phpPath @serverArguments
exit $LASTEXITCODE
