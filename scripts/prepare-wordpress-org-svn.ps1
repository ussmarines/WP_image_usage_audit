param(
    [Parameter(Mandatory = $true)]
    [string]$PluginZip,

    [Parameter(Mandatory = $true)]
    [string]$WorkDir,

    [string]$AssetsDir = ".wordpress-org",
    [string]$Slug = "image-usage-audit",
    [string]$Version = "2.2.9",
    [string]$WordPressUser = "ussmarines",
    [switch]$Commit
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command svn -ErrorAction SilentlyContinue)) {
    throw "The svn command is required."
}
if (-not (Test-Path -LiteralPath $PluginZip -PathType Leaf)) {
    throw "ZIP file not found: $PluginZip"
}
if (-not (Test-Path -LiteralPath $AssetsDir -PathType Container)) {
    throw "Directory asset source not found: $AssetsDir"
}
if (Test-Path -LiteralPath $WorkDir) {
    throw "The working directory already exists: $WorkDir"
}

$svnUrl = "https://plugins.svn.wordpress.org/$Slug"
$temp = Join-Path ([System.IO.Path]::GetTempPath()) ("iua-svn-" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $temp | Out-Null

try {
    svn checkout $svnUrl $WorkDir
    Expand-Archive -LiteralPath $PluginZip -DestinationPath $temp -Force

    $pluginRoot = Join-Path $temp $Slug
    if (-not (Test-Path -LiteralPath $pluginRoot -PathType Container)) {
        throw "The ZIP does not contain the expected root directory: $Slug"
    }

    $trunk = Join-Path $WorkDir "trunk"
    $tags = Join-Path $WorkDir "tags"
    $rootAssets = Join-Path $WorkDir "assets"

    New-Item -ItemType Directory -Path $trunk -Force | Out-Null
    New-Item -ItemType Directory -Path $tags -Force | Out-Null
    New-Item -ItemType Directory -Path $rootAssets -Force | Out-Null

    Copy-Item -Path (Join-Path $pluginRoot "*") -Destination $trunk -Recurse -Force

    foreach ($pattern in @("*.png", "*.jpg", "*.jpeg", "*.gif", "*.svg")) {
        Get-ChildItem -LiteralPath $AssetsDir -Filter $pattern -File | ForEach-Object {
            Copy-Item -LiteralPath $_.FullName -Destination $rootAssets -Force
        }
    }

    Push-Location $WorkDir
    try {
        svn add trunk --force
        svn add assets --force

        Get-ChildItem -LiteralPath $rootAssets -Filter "*.png" -File | ForEach-Object {
            svn propset svn:mime-type image/png $_.FullName
        }
        Get-ChildItem -LiteralPath $rootAssets -Filter "*.jpg" -File | ForEach-Object {
            svn propset svn:mime-type image/jpeg $_.FullName
        }
        Get-ChildItem -LiteralPath $rootAssets -Filter "*.jpeg" -File | ForEach-Object {
            svn propset svn:mime-type image/jpeg $_.FullName
        }
        Get-ChildItem -LiteralPath $rootAssets -Filter "*.gif" -File | ForEach-Object {
            svn propset svn:mime-type image/gif $_.FullName
        }
        Get-ChildItem -LiteralPath $rootAssets -Filter "*.svg" -File | ForEach-Object {
            svn propset svn:mime-type image/svg+xml $_.FullName
        }

        svn copy trunk ("tags/" + $Version)
        svn status

        if ($Commit) {
            $answer = Read-Host "Type PUBLISH to commit the WordPress.org release"
            if ($answer -ne "PUBLISH") {
                throw "Publication cancelled."
            }
            svn commit -m "Publish Image Usage Audit $Version" --username $WordPressUser
        }
        else {
            Write-Host "No SVN commit was performed. Review svn status and svn diff first." -ForegroundColor Green
        }
    }
    finally {
        Pop-Location
    }
}
finally {
    if (Test-Path -LiteralPath $temp) {
        Remove-Item -LiteralPath $temp -Recurse -Force
    }
}
