# Synthetic RPC surface, used only by the Windows qualification child.
param($PlanPath, $ExpectedPlanSha256, $FixturePath, $EntryPath, $Culture, $BindingOperation = '')
[Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($Culture)
$global:probeMock = Get-Content -LiteralPath $FixturePath -Raw | ConvertFrom-Json
$global:probeState = @{ registered = $false; exists = $false; candidate = $false; configuration = ''
    mutations = 0; reloads = 0; productionWrites = 0; creates = 0; deletes = 0 }
function Invoke-WebRequest {
    param([switch] $UseBasicParsing, $Uri, $Method, $ContentType, $Body, $TimeoutSec, $Headers)
    if (-not $UseBasicParsing -or $Body -isnot [byte[]] -or $ContentType -cne 'application/json; charset=utf-8') {
        throw 'Explicit UTF8 byte transport required.'
    }
    $utf8 = [Text.UTF8Encoding]::new($false, $true)
    $reply = Invoke-RestMethod -Uri $Uri -Method $Method -ContentType $ContentType `
        -Body ($utf8.GetString($Body)) -TimeoutSec $TimeoutSec -Headers $Headers
    $bytes = $utf8.GetBytes(($reply | ConvertTo-Json -Depth 20 -Compress))
    return [pscustomobject]@{ RawContentStream = [IO.MemoryStream]::new($bytes, $false) }
}
function Invoke-RestMethod {
    param($Uri, $Method, $ContentType, $Body, $TimeoutSec, $Headers)
    $request = $Body | ConvertFrom-Json
    $m = $request.method; $a = @($request.params)
    $f = $global:probeMock; $s = $global:probeState
    $p = $f.policy
    $testGuid = '{7CB1E964-8C34-4B61-B255-68D86AEC99F2}'
    $testLibrary = '{85DE8006-9775-49E6-BB7E-1924BAA5459A}'
    $testPath = Join-Path (Split-Path -Parent $p.activeModulePath) 'saef-media-carousel-schema-probe'
    $v = $null
    if ($m -in @('IPS_SetParent', 'IPS_SetIdent', 'IPS_SetName', 'IPS_SetHidden',
        'IPS_SetConfiguration', 'IPS_ApplyChanges', 'IPS_DeleteInstance')) {
        if ($a[0] -ne 54321) { $s.productionWrites++; throw 'Production mutation in synthetic test.' }
        $expectedCount = if ($m -in @('IPS_ApplyChanges', 'IPS_DeleteInstance')) { 1 } else { 2 }
        if ($a.Count -ne $expectedCount -or $a[0] -isnot [int]) { throw 'Native parameter count or ID type differs.' }
        if ($m -eq 'IPS_SetParent' -and $a[1] -isnot [int]) { throw 'Native parent type differs.' }
        if ($m -eq 'IPS_SetHidden' -and $a[1] -isnot [bool]) { throw 'Native hidden type differs.' }
        if ($m -in @('IPS_SetIdent', 'IPS_SetName', 'IPS_SetConfiguration') -and $a[1] -isnot [string]) {
            throw 'Native string type differs.'
        }
        $s.mutations++
    }
    if ($m -eq 'IPS_SetConfiguration' -and $f.scenario -in @('rpc-error', 'rpc-error-cleanup', 'rpc-malformed', 'rpc-transport')) {
        if ($f.scenario -eq 'rpc-transport') { throw 'PRIVATE_SENTINEL credential and configuration must not leak.' }
        if ($f.scenario -eq 'rpc-malformed') { return ([pscustomobject]@{ jsonrpc = '2.0'; id = 1 }) }
        return ([pscustomobject]@{ error = [pscustomobject]@{ code = -32602; message = 'PRIVATE_SENTINEL'; data = 'PRIVATE_SENTINEL' } })
    }
    switch ($m) {
        # Later-input failure must not accept the earlier partial observation.
        'IPS_GetKernelRunlevel' { $v = 10103 }
        'IPS_FunctionExists' { $v = $true }
        'IPS_GetFunction' { $v = @{ Parameters = @(@{ Type_ = 1 }, @{ Type_ = 3 }) } }
        'IPS_InstanceExists' { $v = ($a[0] -ne 54321 -or $s.exists) }
        'IPS_GetInstanceListByModuleID' {
            if ($a[0] -eq $testGuid) { $v = @(); if ($s.exists) { $v = @(54321) } }
            else { $v = @($p.expectedInstances | ForEach-Object { $_.instanceId }) }
        }
        'IPS_GetInstance' {
            $guid = $p.moduleGuid
            if ($a[0] -eq 54321) { $guid = $testGuid }
            if ($a[0] -eq $p.moduleControlInstanceId) { $guid = $p.moduleControlModuleGuid }
            $v = @{ InstanceStatus = 102; ModuleInfo = @{ ModuleID = $guid } }
        }
        'IPS_GetObject' {
            $v = @{ ObjectType = 1; ObjectIdent = 'Synthetic'; ObjectName = 'Synthetic'; ParentID = 234
                ObjectPosition = 1; ObjectIsHidden = $false; ObjectIsDisabled = $false; ObjectIsReadOnly = $false }
            if ($a[0] -eq 234) { $v.ObjectType = 0; $v.ObjectIdent = 'ProbeParent'; $v.ParentID = 345 }
        }
        'IPS_GetChildrenIDs' {
            $v = @()
            if ($f.scenario -eq 'foreign-child') { $v = @(65432) }
        }
        'IPS_HasChanges' { $v = $false }
        'IPS_GetConfiguration' {
            if ($a[0] -eq 54321) { $v = $s.configuration }
            else {
                $v = [string] $f.configurations.([string] $a[0])
                if ($f.scenario -eq 'production-drift' -and $s.reloads -gt 0) { $v += ' ' }
            }
        }
        'IPS_LibraryExists' { $v = ($a[0] -ne $testLibrary -or $s.registered) }
        'IPS_ModuleExists' { $v = ($a[0] -ne $testGuid -or $s.registered) }
        'IPS_GetLibrary' { $v = @{ Name = $p.libraryName; URL = $p.libraryUrl } }
        'IPS_GetModule' { $v = @{ LibraryID = $p.libraryGuid; ModuleName = $p.moduleName; ModuleType = 3; Prefix = $p.modulePrefix } }
        'IPS_GetLibraryModules' { $v = @($p.moduleGuid) }
        'MC_ReloadModule' {
            if ($a[1] -cne 'saef-media-carousel-schema-probe') { throw 'Production reload prohibited.' }
            $testAcl = Get-Acl -LiteralPath $testPath
            if (-not $testAcl.AreAccessRulesProtected) { throw 'Unprotected test module reached reload.' }
            foreach ($rule in $testAcl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) {
                if ($rule.IdentityReference.Value -eq 'S-1-5-32-545') { throw 'Shared Users ACE reached test module.' }
            }
            $s.reloads++; $s.registered = $true
            if ((Get-Content (Join-Path $testPath 'SchemaProbe/module.php') -Raw).Contains('ShowFitToggle')) {
                if (-not $s.exists -or $s.candidate) { throw 'Candidate requires one legacy instance.' }
                $s.candidate = $true
                $s.configuration = $s.configuration.TrimEnd('}') + ',"ShowFitToggle":false}'
                if ($f.scenario -eq 'schema-drift') { $s.configuration = $s.configuration.Replace('false', 'true') }
            } else {
                if ($s.exists) { throw 'Do not assume schema downgrade removes properties.' }
                $s.candidate = $false
            }
            $v = $true
        }
        'IPS_CreateInstance' {
            if ($a[0] -cne $testGuid) { throw 'Foreign creation prohibited.' }
            if ($s.exists -or $s.candidate) { throw 'Fresh legacy schema required.' }
            $s.creates++
            $s.exists = $true; $v = 54321
            if ($f.scenario -eq 'creation-response-lost') { throw 'Synthetic uncertain creation.' }
            if ($f.scenario -eq 'zero-id') { $v = 0 }
        }
        'IPS_SetConfiguration' {
            if ($f.scenario -eq 'second-input-fails' -and $s.creates -eq 2) {
                return ([pscustomobject]@{ error = [pscustomobject]@{ code = -32602; message = 'PRIVATE_SENTINEL' } })
            }
            if ($s.candidate) { throw 'Legacy configuration cannot be submitted to candidate schema.' }
            $s.configuration = [string] $a[1]
            $v = $true
        }
        'IPS_SetParent' { $v = $true }
        'IPS_SetIdent' { $v = $true }
        'IPS_SetName' { $v = $true }
        'IPS_SetHidden' { $v = $true }
        'IPS_ApplyChanges' { $v = $true }
        'IPS_DeleteInstance' {
            if ($f.scenario -in @('cleanup-fails', 'rpc-error-cleanup')) { throw 'PRIVATE_SENTINEL cleanup failure.' }
            $s.exists = $false; $v = $true
            $s.deletes++
        }
        'MC_DeleteModule' {
            if ($a[1] -cne 'saef-media-carousel-schema-probe' -or $s.exists) { throw 'Unsafe test library delete.' }
            Remove-Item -LiteralPath $testPath -Recurse -Force
            $s.registered = $false; $v = $true
        }
        default { throw ('Unexpected RPC method: ' + $m) }
    }
    [IO.File]::WriteAllText($f.logPath, ($s | ConvertTo-Json))
    # Match Invoke-RestMethod's JSON object/array types, not PowerShell hashtables.
    return (ConvertTo-Json -InputObject @{ result = $v } -Depth 20 -Compress | ConvertFrom-Json)
}
if ($BindingOperation) {
    & $EntryPath -PlanPath $PlanPath -ExpectedPlanSha256 $ExpectedPlanSha256 `
        -Operation $BindingOperation -Confirmation update-saef-media-carousel-binding
} else {
    & $EntryPath -PlanPath $PlanPath -ExpectedPlanSha256 $ExpectedPlanSha256 -Confirmation qualify-media-carousel-schema
}
exit $LASTEXITCODE
