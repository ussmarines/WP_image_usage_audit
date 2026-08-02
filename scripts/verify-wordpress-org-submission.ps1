param(
    [Parameter(Mandatory = $true)]
    [string]$ZipPath,

    [string]$ExpectedSha256 = "69988a5d02090f74cc9ad09e1990bfb649a0d4cb1df0266e4476cfda53330866"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $ZipPath -PathType Leaf)) {
    throw "ZIP file not found: $ZipPath"
}

$actualHash = (Get-FileHash -LiteralPath $ZipPath -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Host "Calculated SHA-256: $actualHash"

if ($actualHash -ne $ExpectedSha256.ToLowerInvariant()) {
    throw "The checksum does not match the expected public Image Usage Audit 2.2.9 release ZIP."
}

$temp = Join-Path ([System.IO.Path]::GetTempPath()) ("iua-submit-" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $temp | Out-Null

try {
    Expand-Archive -LiteralPath $ZipPath -DestinationPath $temp -Force

    $root = Join-Path $temp "image-usage-audit"
    if (-not (Test-Path -LiteralPath $root -PathType Container)) {
        throw "The ZIP must contain the image-usage-audit root directory."
    }

    $mainFile = Join-Path $root "image-usage-audit.php"
    $readme = Join-Path $root "readme.txt"

    if (-not (Test-Path -LiteralPath $mainFile -PathType Leaf)) {
        throw "The image-usage-audit.php main file is missing."
    }
    if (-not (Test-Path -LiteralPath $readme -PathType Leaf)) {
        throw "The readme.txt file is missing."
    }

    $mainContent = Get-Content -LiteralPath $mainFile -Raw
    $readmeContent = Get-Content -LiteralPath $readme -Raw

    if ($mainContent -notmatch '(?m)^\s*\*\s*Version:\s*2\.2\.9\s*$') {
        throw "Version 2.2.9 was not found in the plugin header."
    }
    if ($mainContent -notmatch '(?m)^\s*\*\s*Text Domain:\s*image-usage-audit\s*$') {
        throw "The image-usage-audit text domain was not found."
    }
    if ($readmeContent -notmatch '(?m)^Stable tag:\s*2\.2\.9\s*$') {
        throw "Stable tag 2.2.9 was not found in readme.txt."
    }

    $forbiddenNames = @(".git", ".github", ".wordpress-org", "node_modules", "tests", "docs", "scripts", "vendor", ".idea", ".vscode")
    foreach ($name in $forbiddenNames) {
        $found = Get-ChildItem -LiteralPath $root -Recurse -Force | Where-Object { $_.Name -eq $name }
        if ($found) {
            throw "Development-only package entry detected: $name"
        }
    }

    $nestedZip = Get-ChildItem -LiteralPath $root -Recurse -File -Filter "*.zip"
    if ($nestedZip) {
        throw "A nested ZIP archive was found inside the plugin package."
    }

    Write-Host ""
    Write-Host "Validation passed." -ForegroundColor Green
    Write-Host "The ZIP matches the expected public Image Usage Audit 2.2.9 package."
}
finally {
    if (Test-Path -LiteralPath $temp) {
        Remove-Item -LiteralPath $temp -Recurse -Force
    }
}
