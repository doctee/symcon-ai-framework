$saefChildProcessSource = @'
using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Diagnostics;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;
using System.Threading.Tasks;

public sealed class SaefChildProcessResult
{
    public int ExitCode { get; set; }
    public string TerminationReason { get; set; }
    public byte[] StandardOutput { get; set; }
    public byte[] StandardError { get; set; }
    public long StandardOutputBytes { get; set; }
    public long StandardErrorBytes { get; set; }
    public long DurationMilliseconds { get; set; }
}

internal sealed class SaefStreamCapture
{
    public byte[] Bytes { get; set; }
    public long TotalBytes { get; set; }
}

internal sealed class SaefOutputBudget
{
    private readonly object sync = new object();
    private readonly int maximumBytes;
    private readonly Action limitExceeded;
    private long totalBytes;
    private int capturedBytes;
    private bool exceeded;

    public SaefOutputBudget(int maximumBytes, Action limitExceeded)
    {
        this.maximumBytes = maximumBytes;
        this.limitExceeded = limitExceeded;
    }

    public int Reserve(int requestedBytes)
    {
        bool notify = false;
        int allowed;
        lock (this.sync)
        {
            this.totalBytes += requestedBytes;
            int remaining = Math.Max(0, this.maximumBytes - this.capturedBytes);
            allowed = Math.Min(requestedBytes, remaining);
            this.capturedBytes += allowed;
            if (this.totalBytes > this.maximumBytes && !this.exceeded)
            {
                this.exceeded = true;
                notify = true;
            }
        }
        if (notify)
        {
            this.limitExceeded();
        }
        return allowed;
    }
}

public static class SaefChildProcessHost
{
    private const uint JobObjectLimitKillOnJobClose = 0x00002000;
    private const int JobObjectExtendedLimitInformationClass = 9;

    [StructLayout(LayoutKind.Sequential)]
    private struct JobObjectBasicLimitInformation
    {
        public long PerProcessUserTimeLimit;
        public long PerJobUserTimeLimit;
        public uint LimitFlags;
        public UIntPtr MinimumWorkingSetSize;
        public UIntPtr MaximumWorkingSetSize;
        public uint ActiveProcessLimit;
        public UIntPtr Affinity;
        public uint PriorityClass;
        public uint SchedulingClass;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct IoCounters
    {
        public ulong ReadOperationCount;
        public ulong WriteOperationCount;
        public ulong OtherOperationCount;
        public ulong ReadTransferCount;
        public ulong WriteTransferCount;
        public ulong OtherTransferCount;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct JobObjectExtendedLimitInformation
    {
        public JobObjectBasicLimitInformation BasicLimitInformation;
        public IoCounters IoInfo;
        public UIntPtr ProcessMemoryLimit;
        public UIntPtr JobMemoryLimit;
        public UIntPtr PeakProcessMemoryUsed;
        public UIntPtr PeakJobMemoryUsed;
    }

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern IntPtr CreateJobObject(IntPtr jobAttributes, string name);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool SetInformationJobObject(
        IntPtr job,
        int informationClass,
        IntPtr information,
        uint informationLength
    );

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool AssignProcessToJobObject(IntPtr job, IntPtr process);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool TerminateJobObject(IntPtr job, uint exitCode);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool CloseHandle(IntPtr handle);

    private static string QuoteArgument(string value)
    {
        if (value == null)
        {
            throw new ArgumentNullException("value");
        }
        if (value.Length == 0)
        {
            return "\"\"";
        }
        bool requiresQuotes = false;
        foreach (char character in value)
        {
            if (char.IsWhiteSpace(character) || character == '"')
            {
                requiresQuotes = true;
                break;
            }
        }
        if (!requiresQuotes)
        {
            return value;
        }

        StringBuilder result = new StringBuilder();
        result.Append('"');
        int backslashes = 0;
        foreach (char character in value)
        {
            if (character == '\\')
            {
                backslashes++;
                continue;
            }
            if (character == '"')
            {
                result.Append('\\', (backslashes * 2) + 1);
                result.Append('"');
                backslashes = 0;
                continue;
            }
            result.Append('\\', backslashes);
            result.Append(character);
            backslashes = 0;
        }
        result.Append('\\', backslashes * 2);
        result.Append('"');
        return result.ToString();
    }

    private static string JoinArguments(IList<string> arguments)
    {
        StringBuilder result = new StringBuilder();
        for (int index = 0; index < arguments.Count; index++)
        {
            if (index > 0)
            {
                result.Append(' ');
            }
            result.Append(QuoteArgument(arguments[index]));
        }
        if (result.Length > 30000)
        {
            throw new ArgumentException("Child process command line exceeds its hard bound.");
        }
        return result.ToString();
    }

    private static void ConfigureKillOnClose(IntPtr job)
    {
        JobObjectExtendedLimitInformation information = new JobObjectExtendedLimitInformation();
        information.BasicLimitInformation.LimitFlags = JobObjectLimitKillOnJobClose;
        int length = Marshal.SizeOf(typeof(JobObjectExtendedLimitInformation));
        IntPtr pointer = Marshal.AllocHGlobal(length);
        try
        {
            Marshal.StructureToPtr(information, pointer, false);
            if (!SetInformationJobObject(job, JobObjectExtendedLimitInformationClass, pointer, (uint)length))
            {
                throw new Win32Exception(Marshal.GetLastWin32Error());
            }
        }
        finally
        {
            Marshal.FreeHGlobal(pointer);
        }
    }

    private static SaefStreamCapture Drain(Stream stream, SaefOutputBudget budget)
    {
        byte[] buffer = new byte[8192];
        long totalBytes = 0;
        using (MemoryStream retained = new MemoryStream())
        {
            int read;
            while ((read = stream.Read(buffer, 0, buffer.Length)) > 0)
            {
                totalBytes += read;
                int retainedBytes = budget.Reserve(read);
                if (retainedBytes > 0)
                {
                    retained.Write(buffer, 0, retainedBytes);
                }
            }
            return new SaefStreamCapture
            {
                Bytes = retained.ToArray(),
                TotalBytes = totalBytes
            };
        }
    }

    public static SaefChildProcessResult Run(
        string executablePath,
        IList<string> arguments,
        string workingDirectory,
        IDictionary<string, string> environment,
        int timeoutMilliseconds,
        int maximumOutputBytes
    )
    {
        ProcessStartInfo startInfo = new ProcessStartInfo();
        startInfo.FileName = executablePath;
        startInfo.Arguments = JoinArguments(arguments);
        startInfo.WorkingDirectory = workingDirectory;
        startInfo.UseShellExecute = false;
        startInfo.CreateNoWindow = true;
        startInfo.RedirectStandardInput = true;
        startInfo.RedirectStandardOutput = true;
        startInfo.RedirectStandardError = true;
        List<string> inheritedSaefKeys = new List<string>();
        foreach (string key in startInfo.EnvironmentVariables.Keys)
        {
            if (key.StartsWith("SAEF_", StringComparison.OrdinalIgnoreCase))
            {
                inheritedSaefKeys.Add(key);
            }
        }
        foreach (string key in inheritedSaefKeys)
        {
            startInfo.EnvironmentVariables.Remove(key);
        }
        foreach (KeyValuePair<string, string> pair in environment)
        {
            startInfo.EnvironmentVariables[pair.Key] = pair.Value;
        }

        IntPtr job = CreateJobObject(IntPtr.Zero, null);
        if (job == IntPtr.Zero)
        {
            throw new Win32Exception(Marshal.GetLastWin32Error());
        }
        Process process = new Process();
        process.StartInfo = startInfo;
        Stopwatch stopwatch = Stopwatch.StartNew();
        int terminationCode = 0;
        Task<SaefStreamCapture> standardOutputTask = null;
        Task<SaefStreamCapture> standardErrorTask = null;
        try
        {
            ConfigureKillOnClose(job);
            if (!process.Start())
            {
                throw new InvalidOperationException("Child process did not start.");
            }
            process.StandardInput.Close();
            if (!AssignProcessToJobObject(job, process.Handle))
            {
                try { process.Kill(); } catch { }
                throw new Win32Exception(Marshal.GetLastWin32Error());
            }

            Action outputLimit = delegate
            {
                if (Interlocked.CompareExchange(ref terminationCode, 1, 0) == 0)
                {
                    TerminateJobObject(job, 91);
                }
            };
            SaefOutputBudget budget = new SaefOutputBudget(maximumOutputBytes, outputLimit);
            standardOutputTask = Task.Factory.StartNew(
                delegate { return Drain(process.StandardOutput.BaseStream, budget); },
                CancellationToken.None,
                TaskCreationOptions.LongRunning,
                TaskScheduler.Default
            );
            standardErrorTask = Task.Factory.StartNew(
                delegate { return Drain(process.StandardError.BaseStream, budget); },
                CancellationToken.None,
                TaskCreationOptions.LongRunning,
                TaskScheduler.Default
            );

            if (!process.WaitForExit(timeoutMilliseconds))
            {
                if (Interlocked.CompareExchange(ref terminationCode, 2, 0) == 0)
                {
                    TerminateJobObject(job, 92);
                }
            }
            if (!process.WaitForExit(5000))
            {
                try { process.Kill(); } catch { }
                process.WaitForExit(5000);
            }
            if (!process.HasExited)
            {
                throw new InvalidOperationException("Child process could not be terminated.");
            }
            int exitCode = process.ExitCode;

            Task[] drains = new Task[] { standardOutputTask, standardErrorTask };
            if (!Task.WaitAll(drains, 5000))
            {
                throw new IOException("Child process output streams did not close.");
            }
            SaefStreamCapture standardOutput = standardOutputTask.Result;
            SaefStreamCapture standardError = standardErrorTask.Result;
            CloseHandle(job);
            job = IntPtr.Zero;
            stopwatch.Stop();
            return new SaefChildProcessResult
            {
                ExitCode = exitCode,
                TerminationReason = terminationCode == 1 ? "output_limit" :
                    (terminationCode == 2 ? "timeout" : "exited"),
                StandardOutput = standardOutput.Bytes,
                StandardError = standardError.Bytes,
                StandardOutputBytes = standardOutput.TotalBytes,
                StandardErrorBytes = standardError.TotalBytes,
                DurationMilliseconds = stopwatch.ElapsedMilliseconds
            };
        }
        finally
        {
            if (job != IntPtr.Zero)
            {
                CloseHandle(job);
            }
            process.Dispose();
            stopwatch.Stop();
        }
    }
}
'@

if ($null -ne ('SaefChildProcessHost' -as [type])) {
    throw [Security.SecurityException]::new('Child process host type already exists before contract import.')
}
Add-Type -TypeDefinition $saefChildProcessSource -Language CSharp -ErrorAction Stop

function Invoke-SaefPowerShellChildProcess {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string] $ScriptPath,
        [Parameter(Mandatory = $true)][ValidatePattern('^[a-f0-9]{64}$')]
        [string] $ExpectedScriptSha256,
        [Parameter()][AllowEmptyCollection()][string[]] $Arguments = @(),
        [Parameter()][hashtable] $Environment = @{},
        [Parameter()][ValidateRange(1, 900)][int] $TimeoutSeconds = 300,
        [Parameter()][ValidateRange(1, 1048576)][int] $MaximumOutputBytes = 65536,
        [Parameter()][string] $WorkingDirectory = ''
    )

    if ($null -eq $Arguments) {
        $Arguments = @()
    }
    if ($null -eq $Environment) {
        $Environment = @{}
    }

    if (-not [IO.Path]::IsPathRooted($ScriptPath) -or
        -not (Test-Path -LiteralPath $ScriptPath -PathType Leaf)) {
        throw [IO.FileNotFoundException]::new('Child process script is missing or not absolute.')
    }
    $scriptItem = Get-Item -LiteralPath $ScriptPath -Force
    if ([long] $scriptItem.Length -lt 1 -or [long] $scriptItem.Length -gt 4194304 -or
        (($scriptItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.IOException]::new('Child process script is unsafe.')
    }
    $actualScriptSha256 = (Get-FileHash -LiteralPath $ScriptPath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualScriptSha256 -cne $ExpectedScriptSha256) {
        throw [Security.SecurityException]::new('Child process script identity differs.')
    }

    if ($Arguments.Count -gt 128) {
        throw [ArgumentException]::new('Child process argument count exceeds its hard bound.')
    }
    $argumentCharacters = 0
    foreach ($argument in $Arguments) {
        if ($null -eq $argument -or $argument.Length -gt 8192 -or
            $argument.IndexOf([char] 0) -ge 0 -or $argument.Contains("`r") -or $argument.Contains("`n")) {
            throw [ArgumentException]::new('Child process argument is invalid.')
        }
        $argumentCharacters += $argument.Length
    }
    if ($argumentCharacters -gt 24576) {
        throw [ArgumentException]::new('Child process arguments exceed their aggregate bound.')
    }

    if ($Environment.Count -gt 16) {
        throw [ArgumentException]::new('Child process environment exceeds its entry bound.')
    }
    $environmentCharacters = 0
    $environmentValues = [Collections.Generic.Dictionary[string, string]]::new(
        [StringComparer]::Ordinal
    )
    foreach ($name in $Environment.Keys) {
        $key = [string] $name
        $value = [string] $Environment[$name]
        if ($key -cnotmatch '^SAEF_[A-Z0-9_]{1,59}$' -or $value.Length -gt 32768 -or
            $value.IndexOf([char] 0) -ge 0 -or $value.Contains("`r") -or $value.Contains("`n")) {
            throw [ArgumentException]::new('Child process environment entry is invalid.')
        }
        $environmentCharacters += $key.Length + $value.Length
        $environmentValues.Add($key, $value)
    }
    if ($environmentCharacters -gt 65536) {
        throw [ArgumentException]::new('Child process environment exceeds its aggregate bound.')
    }

    if ([string]::IsNullOrWhiteSpace($WorkingDirectory)) {
        $WorkingDirectory = Split-Path -Parent $ScriptPath
    }
    if (-not [IO.Path]::IsPathRooted($WorkingDirectory) -or
        -not (Test-Path -LiteralPath $WorkingDirectory -PathType Container) -or
        (((Get-Item -LiteralPath $WorkingDirectory -Force).Attributes -band
            [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.DirectoryNotFoundException]::new('Child process working directory is unsafe.')
    }

    $powerShellExecutable = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    if (-not (Test-Path -LiteralPath $powerShellExecutable -PathType Leaf) -or
        (((Get-Item -LiteralPath $powerShellExecutable -Force).Attributes -band
            [IO.FileAttributes]::ReparsePoint) -ne 0)) {
        throw [IO.FileNotFoundException]::new('Windows PowerShell 5.1 executable is missing or unsafe.')
    }
    $childArguments = [Collections.Generic.List[string]]::new()
    foreach ($value in @('-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $ScriptPath)) {
        $childArguments.Add([string] $value)
    }
    foreach ($value in $Arguments) {
        $childArguments.Add($value)
    }

    $result = [SaefChildProcessHost]::Run(
        $powerShellExecutable,
        $childArguments,
        $WorkingDirectory,
        $environmentValues,
        $TimeoutSeconds * 1000,
        $MaximumOutputBytes
    )
    if ($result.TerminationReason -cne 'exited') {
        $exception = if ($result.TerminationReason -ceq 'timeout') {
            [TimeoutException]::new('Child process exceeded its runtime bound.')
        } else {
            [InvalidOperationException]::new('Child process exceeded its output bound.')
        }
        $exception.Data['terminationReason'] = $result.TerminationReason
        $exception.Data['standardOutputBytes'] = $result.StandardOutputBytes
        $exception.Data['standardErrorBytes'] = $result.StandardErrorBytes
        $exception.Data['durationMilliseconds'] = $result.DurationMilliseconds
        throw $exception
    }

    return [pscustomobject]@{
        formatVersion = 1
        exitCode = [int] $result.ExitCode
        terminationReason = [string] $result.TerminationReason
        standardOutput = [byte[]] $result.StandardOutput
        standardError = [byte[]] $result.StandardError
        standardOutputBytes = [long] $result.StandardOutputBytes
        standardErrorBytes = [long] $result.StandardErrorBytes
        durationMilliseconds = [long] $result.DurationMilliseconds
    }
}
