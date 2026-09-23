# Synthetic RPC surface, used only by the Windows qualification child.
param($PlanPath, $ExpectedPlanSha256, $FixturePath, $EntryPath, $Culture)
[Threading.Thread]::CurrentThread.CurrentCulture = [Globalization.CultureInfo]::GetCultureInfo($Culture)
$global:probeMock = Get-Content -LiteralPath $FixturePath -Raw | ConvertFrom-Json
$global:probeState = @{ registered = $false; exists = $false; candidate = $false; configuration = ''
    mutations = 0; reloads = 0; productionWrites = 0 }
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
        $s.mutations++
    }
    switch ($m) {
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
            $s.reloads++; $s.registered = $true
            if ((Get-Content (Join-Path $testPath 'SchemaProbe/module.php') -Raw).Contains('ShowFitToggle')) {
                $s.candidate = $true
                $s.configuration = $s.configuration.TrimEnd('}') + ',"ShowFitToggle":false}'
                if ($f.scenario -eq 'schema-drift') { $s.configuration = $s.configuration.Replace('false', 'true') }
            }
            $v = $true
        }
        'IPS_CreateInstance' {
            if ($a[0] -cne $testGuid) { throw 'Foreign creation prohibited.' }
            $s.exists = $true; $v = 54321
            if ($f.scenario -eq 'creation-response-lost') { throw 'Synthetic uncertain creation.' }
            if ($f.scenario -eq 'zero-id') { $v = 0 }
        }
        'IPS_SetConfiguration' {
            $s.configuration = [string] $a[1]
            if ($s.candidate) { $s.configuration = $s.configuration.TrimEnd('}') + ',"ShowFitToggle":false}' }
            $v = $true
        }
        'IPS_SetParent' { $v = $true }
        'IPS_SetIdent' { $v = $true }
        'IPS_SetName' { $v = $true }
        'IPS_SetHidden' { $v = $true }
        'IPS_ApplyChanges' { $v = $true }
        'IPS_DeleteInstance' {
            if ($f.scenario -eq 'cleanup-fails') { throw 'Synthetic cleanup failure.' }
            $s.exists = $false; $v = $true
        }
        'MC_DeleteModule' {
            if ($a[1] -cne 'saef-media-carousel-schema-probe' -or $s.exists) { throw 'Unsafe test library delete.' }
            Remove-Item -LiteralPath $testPath -Recurse -Force
            $s.registered = $false; $v = $true
        }
        default { throw ('Unexpected RPC method: ' + $m) }
    }
    [IO.File]::WriteAllText($f.logPath, ($s | ConvertTo-Json))
    return @{ result = $v }
}
& $EntryPath -PlanPath $PlanPath -ExpectedPlanSha256 $ExpectedPlanSha256 -Confirmation qualify-media-carousel-schema
exit $LASTEXITCODE
