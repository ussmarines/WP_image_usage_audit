[CmdletBinding()] param([ValidateSet('Quick','Full')][string]$Profile='Full',[switch]$Enforce)
$ErrorActionPreference='Stop';$a=@((Join-Path $PSScriptRoot 'run_security_suite.py'),'--profile',$Profile.ToLowerInvariant());if($Enforce){$a+='--enforce'};& py -3 @a;exit $LASTEXITCODE
