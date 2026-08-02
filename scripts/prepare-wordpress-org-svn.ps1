param(
	[Parameter(Mandatory = $true)][string] $PluginZip,
	[Parameter(Mandatory = $true)][string] $WorkDir,
	[Parameter(Mandatory = $true)][string] $AssetsDir,
	[string] $Slug = 'image-usage-audit',
	[string] $Version = '3.0.0',
	[string] $WordPressUser = 'ussmarines',
	[switch] $Commit
)
$ErrorActionPreference = 'Stop'
if (-not (Get-Command svn -ErrorAction SilentlyContinue)) { throw 'The svn command is required.' }
if (-not (Test-Path -LiteralPath $PluginZip -PathType Leaf)) { throw "Plugin ZIP not found: $PluginZip" }
if (-not (Test-Path -LiteralPath $AssetsDir -PathType Container)) { throw "Assets directory not found: $AssetsDir" }
if (Test-Path -LiteralPath $WorkDir) { throw "Working directory already exists: $WorkDir" }
$svnUrl = "https://plugins.svn.wordpress.org/$Slug"
$temp = Join-Path ([System.IO.Path]::GetTempPath()) ('iua-svn-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp | Out-Null
try {
	svn checkout $svnUrl $WorkDir
	Expand-Archive -LiteralPath $PluginZip -DestinationPath $temp -Force
	$pluginRoot = Join-Path $temp $Slug
	if (-not (Test-Path -LiteralPath $pluginRoot -PathType Container)) { throw "Expected ZIP root missing: $Slug" }
	$trunk = Join-Path $WorkDir 'trunk'; $tags = Join-Path $WorkDir 'tags'; $rootAssets = Join-Path $WorkDir 'assets'
	New-Item -ItemType Directory -Path $trunk -Force | Out-Null
	New-Item -ItemType Directory -Path $tags -Force | Out-Null
	New-Item -ItemType Directory -Path $rootAssets -Force | Out-Null
	Copy-Item -Path (Join-Path $pluginRoot '*') -Destination $trunk -Recurse -Force
	Copy-Item -Path (Join-Path $AssetsDir '*') -Destination $rootAssets -Recurse -Force
	Push-Location $WorkDir
	try {
		svn add trunk --force; svn add assets --force
		Get-ChildItem -LiteralPath $rootAssets -Filter '*.png' -File | ForEach-Object { svn propset svn:mime-type image/png $_.FullName }
		Get-ChildItem -LiteralPath $rootAssets -Filter '*.jpg' -File | ForEach-Object { svn propset svn:mime-type image/jpeg $_.FullName }
		if (Test-Path -LiteralPath (Join-Path $tags $Version)) { throw "Tag already exists: $Version" }
		svn copy trunk ("tags/" + $Version)
		svn status
		if ($Commit) {
			if ((Read-Host 'Type PUBLISH to commit') -ne 'PUBLISH') { throw 'SVN publication cancelled.' }
			svn commit -m "Publish Image Usage Audit $Version" --username $WordPressUser
		} else { Write-Host 'No SVN commit performed. Review svn status and svn diff.' -ForegroundColor Green }
	}
	finally { Pop-Location }
}
finally { if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Recurse -Force } }
