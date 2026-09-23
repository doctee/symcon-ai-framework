# Real Windows PowerShell 5.1 child-process qualification, synthetic paths only.
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSEdition -cne 'Desktop' -or $PSVersionTable.PSVersion.Major -ne 5) {
    throw 'Windows PowerShell 5.1 required.'
}
$windows = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../deployments/symcon/windows'))
$source = Join-Path $windows 'adapters/Initialize-SaefOwnTracksPositionMapAdapterState.ps1'
$tokens = $null; $errors = $null
$ast = [Management.Automation.Language.Parser]::ParseFile($source, [ref] $tokens, [ref] $errors)
if (@($errors).Count) { throw ($errors | Out-String) }
foreach ($fn in @($ast.FindAll({ param($n)
    $n -is [Management.Automation.Language.FunctionDefinitionAst]
}, $false))) { . ([scriptblock]::Create($fn.Extent.Text)) }
. (Join-Path $windows 'SaefChildProcess.ps1')
Assert-Elevated
$account = [Security.Principal.WindowsIdentity]::GetCurrent()
$user = $account.Name.Split('\')[-1]
$sid = $account.User.Value
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('saef-state-test-' + [Guid]::NewGuid().ToString('N'))
$null = [IO.Directory]::CreateDirectory($scratch)
Set-RestrictedAcl $scratch $sid
$sourceHash = Get-Sha256 $source
$passed = 0
function Assert-Test { param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}
function Invoke-State { param([string] $Operation, [string] $ExpectedOutcome, [int] $ExpectedExit = 0,
    [string] $Hash = $script:policyHash, [string] $Confirmation = $script:confirmation,
    [string] $Status = $script:status, [string] $SelectedProfile = $script:profile)
    $arguments = @('-Operation', $Operation, '-AdapterPolicyPath', $script:policyPath,
        '-ExpectedAdapterPolicySha256', $Hash, '-DeploymentUser', $user,
        '-StatusPath', $Status, '-Confirmation', $Confirmation)
    # OwnTracks default must remain source-compatible, without a new argument.
    if ($SelectedProfile -cne 'default') { $arguments += @('-AdapterProfile', $SelectedProfile) }
    $child = Invoke-SaefPowerShellChildProcess -ScriptPath $source -ExpectedScriptSha256 $sourceHash `
        -Arguments $arguments -TimeoutSeconds 60 -MaximumOutputBytes 131072
    Assert-Test ($child.terminationReason -ceq 'exited' -and $child.exitCode -eq $ExpectedExit) `
        ('Unexpected child result: ' + $child.exitCode + ' ' + [Text.Encoding]::UTF8.GetString($child.standardError))
    if ($ExpectedOutcome) {
        $result = Get-Content -LiteralPath $Status -Raw -Encoding UTF8 | ConvertFrom-Json
        Assert-Test ($result.outcome -ceq $ExpectedOutcome) ('Unexpected outcome: ' + $result.outcome)
        foreach ($flag in @('activeModuleMutationAttempted', 'installedChannelMutationAttempted',
            'targetAllowlistMutationAttempted', 'moduleActivationAttempted', 'symconRpcContactAttempted')) {
            Assert-Test (-not $result.$flag) ('Unexpected action: ' + $flag)
        }
    }
    $script:passed++
}
try {
    foreach ($profile in @('default', 'saef-media-carousel-v1')) {
        $fixture = Join-Path $scratch $profile
        $null = [IO.Directory]::CreateDirectory($fixture)
        Set-RestrictedAcl $fixture $sid
        $active = Join-Path $fixture 'module'
        $null = [IO.Directory]::CreateDirectory($active)
        $root = Join-Path $fixture 'state'
        $policyPath = Join-Path $fixture 'policy.local.json'
        $status = Join-Path $fixture 'status.local.json'
        $target = if ($profile -ceq 'default') { 'saef-owntracks-position-map' } else { 'saef-media-carousel' }
        $confirmation = 'provision-' + $target + '-adapter-state'
        $policy = [ordered]@{ formatVersion = 1; adapterProfile = $target + '-v1'; targetId = $target
            activeModulePath = $active; adapterStateRoot = $root; quiescenceTimeoutSeconds = 1
            libraryGuid = '{8D263598-06EF-4440-982C-9E86E3F8D130}'
            moduleGuid = '{41D0C5ED-8331-4B26-A44E-6FDCEC1BC41F}'
            mutexName = 'Global\SAEF.MediaCarousel.ModuleAdapter' }
        [IO.File]::WriteAllText($policyPath, ($policy | ConvertTo-Json), [Text.UTF8Encoding]::new($false))
        $policyHash = Get-Sha256 $policyPath
        Invoke-State preflight ready
        Assert-Test (-not (Test-Path -LiteralPath $root)) 'Preflight created state root.'
        Invoke-State install failed 20 -Hash ('a' * 64)
        Invoke-State install failed 20 -Confirmation wrong
        if ($profile -cne 'default') { Invoke-State install failed 20 -SelectedProfile default }
        Assert-Test (-not (Test-Path -LiteralPath $root)) 'Rejected operation mutated state.'
        # Status write failure after creation must remove only its own still-empty leaf.
        $badStatus = Join-Path $fixture 'status-directory'
        $null = [IO.Directory]::CreateDirectory($badStatus)
        Invoke-State install '' 30 -Status $badStatus
        Assert-Test (-not (Test-Path -LiteralPath $root)) 'Empty-leaf rollback failed.'
        Invoke-State install installed
        Assert-ProtectedAcl $root $sid
        $aclBefore = (Get-Acl -LiteralPath $root).Sddl
        $sentinel = Join-Path $root 'preserve.txt'
        [IO.File]::WriteAllText($sentinel, 'preserve existing adapter state')
        Invoke-State install already_present
        Assert-Test ((Get-Acl -LiteralPath $root).Sddl -ceq $aclBefore) 'Existing ACL changed.'
        Assert-Test ([IO.File]::ReadAllText($sentinel) -ceq 'preserve existing adapter state') 'Existing state changed.'
    }
    Write-Output ('PASS: adapter state profiles, ' + $passed + ' real Windows child scenarios.')
} finally {
    # Only this newly generated synthetic fixture is removed; no live paths are used.
    if ($scratch -and (Split-Path -Leaf $scratch) -match '^saef-state-test-[a-f0-9]{32}$') {
        Remove-Item -LiteralPath $scratch -Recurse -Force
    }
}
