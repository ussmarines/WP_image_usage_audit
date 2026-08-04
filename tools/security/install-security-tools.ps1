[CmdletBinding()]
param([switch]$Force)
$ErrorActionPreference='Stop'
$Manifest=Join-Path $env:LOCALAPPDATA 'ussmarines-security-tools\installed-tools.json'
if(Test-Path $Manifest){
 $Tools=(Get-Content -Raw $Manifest|ConvertFrom-Json).tools
 $Expected=@{opengrep='1.22.0';trivy='0.70.0';gitleaks='8.30.1';zizmor='1.26.1'}
 foreach($Name in $Expected.Keys){if($Tools.$Name.version-ne$Expected[$Name]){throw "Version partagée incorrecte pour $Name. Relance l’installateur canonique."}}
 Write-Host 'Les outils partagés vérifiés sont déjà installés.' -ForegroundColor Green
 exit 0
}
throw 'Installe les outils une seule fois depuis SpaceShooter-2D-web ou MailPerch avec .\tools\security\install-security-tools.ps1, puis relance ce script.'
