# Hostinger FTP packaging — produces app.zip + public.zip in deploy/dist/
# Does NOT upload. No live FTP. Local builds only.
# Usage:  powershell -File deploy/package.ps1
#         powershell -File deploy/package.ps1 -SkipBuild -DryRun

[CmdletBinding()]
param(
    [switch]$SkipBuild,
    [switch]$SkipComposer,
    [switch]$NoRestoreDev,
    [switch]$DryRun,
    [switch]$Help
)

$ErrorActionPreference = "Stop"

function Show-Help {
    @"
Usage: deploy/package.ps1 [options]

Build Hostinger-ready archive pair (no FTP upload):
  deploy/dist/app.zip     — Laravel app (incl. vendor --no-dev), without public/
  deploy/dist/public.zip  — contents of public/ (built assets + .htaccess + dual-path index.php)

Options:
  -SkipBuild       Skip npm run build
  -SkipComposer    Skip composer --no-dev; use current vendor/
  -NoRestoreDev    After packaging, do not restore full composer install
  -DryRun          Print steps only
  -Help            Show this help

Recommended order (default):
  1. npm run build
  2. composer install --no-dev --optimize-autoloader
  3. zip app + public → deploy/dist/
  4. composer install (restore local dev / tests)
"@
}

if ($Help) {
    Show-Help
    exit 0
}

$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Dist = Join-Path $Root "deploy\dist"
$ComposerRan = $false

function Step([string]$Message) { Write-Host "==> $Message" }

function Invoke-OrDry {
    param([scriptblock]$Block, [string]$Description)
    if ($DryRun) {
        Write-Host "    [dry-run] $Description"
    } else {
        & $Block
    }
}

Set-Location $Root
Step "Root: $Root"
Step "Output: $Dist"

if (-not $SkipBuild) {
    Step "npm run build"
    Invoke-OrDry -Description "npm run build" -Block { npm run build }
} else {
    Step "Skip npm run build"
}

if (-not $SkipComposer) {
    Step "composer install --no-dev --optimize-autoloader"
    Invoke-OrDry -Description "composer install --no-dev" -Block {
        composer install --no-dev --optimize-autoloader --no-interaction
    }
    $ComposerRan = $true
} else {
    Step "Skip composer --no-dev"
}

function Restore-Dev {
    if ($ComposerRan -and -not $NoRestoreDev -and -not $DryRun) {
        Step "Restoring full composer install for local dev"
        composer install --no-interaction
    }
}

try {
    if ($DryRun) {
        Step "Would create $Dist\app.zip and $Dist\public.zip"
        exit 0
    }

    New-Item -ItemType Directory -Force -Path $Dist | Out-Null
    $AppZip = Join-Path $Dist "app.zip"
    $PublicZip = Join-Path $Dist "public.zip"
    if (Test-Path $AppZip) { Remove-Item $AppZip -Force }
    if (Test-Path $PublicZip) { Remove-Item $PublicZip -Force }

    $Stage = Join-Path ([System.IO.Path]::GetTempPath()) ("portfolio-os-package-" + [guid]::NewGuid().ToString("n"))
    $StageApp = Join-Path $Stage "app"
    New-Item -ItemType Directory -Force -Path $StageApp | Out-Null

    Step "Staging app tree → $StageApp"

    $excludeDirs = @(
        'public', 'node_modules', '.git', 'tests', 'deploy\dist', 'dist',
        'storage\logs', 'storage\framework\cache', 'storage\framework\sessions',
        'storage\framework\views', 'storage\framework\testing', 'storage\pail'
    )
    $excludeFiles = @(
        '.env', '.env.backup', '.env.production', '.phpunit.result.cache', 'phpunit.xml'
    )

    Get-ChildItem -Path $Root -Force | ForEach-Object {
        $name = $_.Name
        if ($excludeDirs -contains $name) { return }
        if ($excludeFiles -contains $name) { return }
        if ($name -eq 'database' -and $_.PSIsContainer) {
            $dbDest = Join-Path $StageApp 'database'
            New-Item -ItemType Directory -Force -Path $dbDest | Out-Null
            Get-ChildItem $_.FullName -Force | Where-Object {
                $_.Name -notmatch '\.sqlite'
            } | ForEach-Object {
                Copy-Item $_.FullName -Destination (Join-Path $dbDest $_.Name) -Recurse -Force
            }
            return
        }
        if ($name -eq 'storage' -and $_.PSIsContainer) {
            # handled via skeleton below after full copy attempt
            $storageSrc = $_.FullName
            $storageDest = Join-Path $StageApp 'storage'
            # copy storage but strip log/cache bodies via robocopy-like manual filter
            robocopy $storageSrc $storageDest /E /NFL /NDL /NJH /NJS /nc /ns /np `
                /XD logs cache sessions views testing pail `
                /XF *.log | Out-Null
            return
        }
        if ($name -eq 'deploy' -and $_.PSIsContainer) {
            $deployDest = Join-Path $StageApp 'deploy'
            New-Item -ItemType Directory -Force -Path $deployDest | Out-Null
            Get-ChildItem $_.FullName -Force | Where-Object { $_.Name -ne 'dist' } | ForEach-Object {
                Copy-Item $_.FullName -Destination (Join-Path $deployDest $_.Name) -Recurse -Force
            }
            return
        }
        Copy-Item $_.FullName -Destination (Join-Path $StageApp $name) -Recurse -Force
    }

    # Skeleton storage dirs
    @(
        'storage\app\public',
        'storage\app\private',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'storage\logs',
        'bootstrap\cache'
    ) | ForEach-Object {
        New-Item -ItemType Directory -Force -Path (Join-Path $StageApp $_) | Out-Null
    }

    # Remove secrets if any
    @('.env', '.env.backup', '.env.production') | ForEach-Object {
        $p = Join-Path $StageApp $_
        if (Test-Path $p) { Remove-Item $p -Force }
    }

    if (-not (Test-Path (Join-Path $StageApp 'vendor'))) {
        throw "vendor/ missing in package stage. Run without -SkipComposer or install deps first."
    }

    Step "Creating app.zip"
    Compress-Archive -Path (Join-Path $StageApp '*') -DestinationPath $AppZip -CompressionLevel Optimal

    Step "Creating public.zip"
    $publicPath = Join-Path $Root 'public'
    if (-not (Test-Path (Join-Path $publicPath 'index.php'))) {
        throw "public/index.php missing"
    }
    $PublicStage = Join-Path $Stage 'public'
    New-Item -ItemType Directory -Force -Path $PublicStage | Out-Null
    Get-ChildItem -Path $publicPath -Force | Where-Object {
        $_.Name -notin @('storage', 'hot')
    } | ForEach-Object {
        Copy-Item $_.FullName -Destination (Join-Path $PublicStage $_.Name) -Recurse -Force
    }
    Compress-Archive -Path (Join-Path $PublicStage '*') -DestinationPath $PublicZip -CompressionLevel Optimal

    Step "Done"
    Get-Item $AppZip, $PublicZip | Format-Table Name, Length, FullName -AutoSize
    Write-Host ""
    Write-Host "Next: see DEPLOYMENT.md — extract app.zip → laravel_app/, public.zip → public_html/"
}
finally {
    if (Test-Path $Stage) {
        Remove-Item $Stage -Recurse -Force -ErrorAction SilentlyContinue
    }
    Restore-Dev
}
