param(
	[Parameter(ValueFromRemainingArguments = $true)]
	[string[]] $ActionlintArgs
)

$ErrorActionPreference = 'Stop'
$version = '1.7.12'
$tempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$tempPrefix = [System.IO.Path]::TrimEndingDirectorySeparator($tempRoot) + [System.IO.Path]::DirectorySeparatorChar
$stagingBase = [System.IO.Path]::GetFullPath((Join-Path $tempRoot ('pixcensus-actionlint-' + [System.Guid]::NewGuid().ToString('N'))))

if (-not $stagingBase.StartsWith($tempPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
	throw 'The actionlint staging directory must stay inside the system temporary directory.'
}

if ($IsWindows -and [System.Runtime.InteropServices.RuntimeInformation]::OSArchitecture -eq 'X64') {
	$archiveName = "actionlint_${version}_windows_amd64.zip"
	$expectedSha256 = '6e7241b51e6817ea6a047693d8e6fed13b31819c9a0dd6c5a726e1592d22f6e9'
	$binaryName = 'actionlint.exe'
} elseif ($IsLinux -and [System.Runtime.InteropServices.RuntimeInformation]::OSArchitecture -eq 'X64') {
	$archiveName = "actionlint_${version}_linux_amd64.tar.gz"
	$expectedSha256 = '8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8'
	$binaryName = 'actionlint'
} else {
	throw 'This verified actionlint wrapper currently supports Windows x64 and Linux x64.'
}

$archivePath = Join-Path $stagingBase $archiveName
$downloadUrl = "https://github.com/rhysd/actionlint/releases/download/v${version}/${archiveName}"
$exitCode = 1

try {
	New-Item -ItemType Directory -Path $stagingBase | Out-Null
	Invoke-WebRequest -Uri $downloadUrl -OutFile $archivePath

	$actualSha256 = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()

	if ($actualSha256 -ne $expectedSha256) {
		throw "actionlint SHA-256 mismatch: $actualSha256"
	}

	if ($IsWindows) {
		Expand-Archive -LiteralPath $archivePath -DestinationPath $stagingBase
	} else {
		& tar -xzf $archivePath -C $stagingBase
		if ($LASTEXITCODE -ne 0) {
			throw "Unable to extract actionlint: exit $LASTEXITCODE"
		}
	}

	& (Join-Path $stagingBase $binaryName) @ActionlintArgs
	$exitCode = $LASTEXITCODE
} finally {
	if (Test-Path -LiteralPath $stagingBase) {
		Remove-Item -LiteralPath $stagingBase -Recurse -Force
	}
}

exit $exitCode
