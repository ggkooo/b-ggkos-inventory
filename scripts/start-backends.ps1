param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [int[]]$Ports = @(9001, 9002, 9003, 9004, 9005),
    [switch]$StopExistingOnPorts,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$stateDir = Join-Path $ProjectRoot 'storage\framework\backend-servers'
$stateFile = Join-Path $stateDir 'servers.json'

New-Item -ItemType Directory -Path $stateDir -Force | Out-Null

function Get-ListeningProcessId {
    param([int]$Port)

    try {
        $connection = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction Stop | Select-Object -First 1
        if ($null -ne $connection) {
            return [int]$connection.OwningProcess
        }
    } catch {
        return $null
    }

    return $null
}

function Stop-ProcessSafe {
    param([int]$ProcessId)

    try {
        Stop-Process -Id $ProcessId -Force -ErrorAction Stop
        return $true
    } catch {
        return $false
    }
}

$artisanPath = Join-Path $ProjectRoot 'artisan'

if (-not (Test-Path $artisanPath)) {
    throw "Could not find artisan at: $artisanPath"
}

if ($StopExistingOnPorts) {
    foreach ($port in $Ports) {
        $existingPid = Get-ListeningProcessId -Port $port
        if ($null -ne $existingPid) {
            if ($DryRun) {
                Write-Host "[DRY-RUN] Would stop PID $existingPid on port $port"
            } else {
                $stopped = Stop-ProcessSafe -ProcessId $existingPid
                if ($stopped) {
                    Write-Host "Stopped existing PID $existingPid on port $port"
                } else {
                    Write-Warning "Could not stop existing PID $existingPid on port $port"
                }
            }
        }
    }
}

$newState = @()

foreach ($port in $Ports) {
    $stdoutLogFile = Join-Path $stateDir "backend-$port.stdout.log"
    $stderrLogFile = Join-Path $stateDir "backend-$port.stderr.log"
    $existingPid = Get-ListeningProcessId -Port $port

    if ($null -ne $existingPid) {
        Write-Host "Port $port already listening (PID $existingPid)."
        $newState += [pscustomobject]@{
            port = $port
            pid = $existingPid
            stdout_log = $stdoutLogFile
            stderr_log = $stderrLogFile
            started_at = (Get-Date).ToString('o')
        }
        continue
    }

    $command = 'set BACKEND_SERVER_ROLE=backend&& php "{0}" serve --host=127.0.0.1 --port={1}' -f $artisanPath, $port

    if ($DryRun) {
        Write-Host "[DRY-RUN] Would start: cmd.exe /c $command"
        continue
    }

    $process = Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', $command -WorkingDirectory $ProjectRoot -RedirectStandardOutput $stdoutLogFile -RedirectStandardError $stderrLogFile -PassThru -WindowStyle Hidden

    $process.Refresh()

    if ($process.HasExited) {
        Write-Warning "Backend on port $port exited immediately. Check: $stdoutLogFile and $stderrLogFile"
        continue
    }

    Write-Host "Started backend on port $port (PID $($process.Id))"

    $newState += [pscustomobject]@{
        port = $port
        pid = $process.Id
        stdout_log = $stdoutLogFile
        stderr_log = $stderrLogFile
        started_at = (Get-Date).ToString('o')
    }
}

if (-not $DryRun) {
    $newState | ConvertTo-Json -Depth 5 | Set-Content -Path $stateFile -Encoding UTF8
    Write-Host "Saved state file: $stateFile"
}

Write-Host 'Done.'
