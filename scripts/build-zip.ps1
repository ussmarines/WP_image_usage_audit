param(
	[string] $OutputPath = 'dist/image-usage-audit.zip'
)

$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$repoPrefix = [System.IO.Path]::TrimEndingDirectorySeparator($repoRoot) + [System.IO.Path]::DirectorySeparatorChar
$outputFullPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $OutputPath))

if (-not $outputFullPath.StartsWith($repoPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
	throw 'The ZIP output path must stay inside the repository.'
}

$tempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$tempPrefix = [System.IO.Path]::TrimEndingDirectorySeparator($tempRoot) + [System.IO.Path]::DirectorySeparatorChar
$stagingBase = [System.IO.Path]::GetFullPath((Join-Path $tempRoot ('iua-package-' + [System.Guid]::NewGuid().ToString('N'))))

if (-not $stagingBase.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
	throw 'The staging directory must stay inside the system temporary directory.'
}

$packageRoot = Join-Path $stagingBase 'image-usage-audit'
$runtimeFiles = @(
	'image-usage-audit.php',
	'uninstall.php',
	'readme.txt',
	'LICENSE'
)
$runtimeDirectories = @(
	'assets',
	'includes',
	'languages',
	'views'
)

try {
	New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

	foreach ($relativePath in $runtimeFiles) {
		Copy-Item -LiteralPath (Join-Path $repoRoot $relativePath) -Destination (Join-Path $packageRoot $relativePath)
	}

	foreach ($relativePath in $runtimeDirectories) {
		Copy-Item -LiteralPath (Join-Path $repoRoot $relativePath) -Destination (Join-Path $packageRoot $relativePath) -Recurse
	}

	$fixedTimestamp = [System.DateTime]::SpecifyKind([System.DateTime]'2000-01-01T00:00:00', [System.DateTimeKind]::Utc)
	Get-ChildItem -LiteralPath $stagingBase -Recurse -File | ForEach-Object { $_.LastWriteTimeUtc = $fixedTimestamp }

	$outputDirectory = Split-Path -Parent $outputFullPath
	New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null

	if (Test-Path -LiteralPath $outputFullPath) {
		Remove-Item -LiteralPath $outputFullPath -Force
	}

	Compress-Archive -LiteralPath $packageRoot -DestinationPath $outputFullPath -CompressionLevel Optimal

	Add-Type -AssemblyName System.IO.Compression.FileSystem
	$archive = [System.IO.Compression.ZipFile]::OpenRead($outputFullPath)

	try {
		$entries = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
		$required = @(
			'image-usage-audit/image-usage-audit.php',
			'image-usage-audit/readme.txt',
			'image-usage-audit/LICENSE',
			'image-usage-audit/uninstall.php'
		)

		foreach ($entry in $entries) {
			if (-not $entry.StartsWith('image-usage-audit/', [System.StringComparison]::Ordinal)) {
				throw "Unexpected ZIP root entry: $entry"
			}

			if ($entry -match '(^|/)(\.git|\.github|\.agents|\.codex|docs|tests|node_modules|vendor|scripts|dist)(/|$)') {
				throw "Development-only ZIP entry: $entry"
			}
		}

		foreach ($entry in $required) {
			if ($entries -notcontains $entry) {
				throw "Missing required ZIP entry: $entry"
			}
		}

		$mainEntry = $archive.GetEntry('image-usage-audit/image-usage-audit.php')
		$reader = [System.IO.StreamReader]::new($mainEntry.Open())
		try { $mainContent = $reader.ReadToEnd() } finally { $reader.Dispose() }

		if ($mainContent -notmatch 'Version:\s+2\.2\.5' -or $mainContent -notmatch 'License:\s+GPL-2\.0-or-later') {
			throw 'Plugin version or license metadata is inconsistent in the ZIP.'
		}
	} finally {
		$archive.Dispose()
	}

	[pscustomobject]@{
		zip = $outputFullPath
		entries = $entries.Count
		root = 'image-usage-audit/'
		result = 'pass'
	} | ConvertTo-Json -Compress
} finally {
	if (Test-Path -LiteralPath $stagingBase) {
		Remove-Item -LiteralPath $stagingBase -Recurse -Force
	}
}
