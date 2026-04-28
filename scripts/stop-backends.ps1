param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [int[]]$Ports = @(9001, 9002, 9003, 9004, 9005),
    [switch]$IncludeUnknownPortListeners,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$stateDir = Join-Path $ProjectRoot 'storage\framework\backend-servers'
$stateFile = Join-Path $stateDir 'servers.json'

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

$seen = @{}

if (Test-Path $stateFile) {
    $raw = Get-Content -Path $stateFile -Raw

    if ($raw.Trim() -ne '') {
        $entries = $raw | ConvertFrom-Json

        foreach ($entry in @($entries)) {
            if ($null -eq $entry.pid) {
                continue
            }

            $trackedProcessId = [int]$entry.pid

            if ($seen.ContainsKey($trackedProcessId)) {
                continue
            }

            $seen[$trackedProcessId] = $true

            if ($DryRun) {
                Write-Host "[DRY-RUN] Would stop PID $trackedProcessId (state file)"
            } else {
                $stopped = Stop-ProcessSafe -ProcessId $trackedProcessId
                if ($stopped) {
                    Write-Host "Stopped PID $trackedProcessId (state file)"
                } else {
                    Write-Host "PID $trackedProcessId not running or could not be stopped"
                }
            }
        }
    }
}

if ($IncludeUnknownPortListeners) {
    foreach ($port in $Ports) {
        $processIdOnPort = Get-ListeningProcessId -Port $port

        if ($null -eq $processIdOnPort) {
            continue
        }

        if ($seen.ContainsKey($processIdOnPort)) {
            continue
        }

        $seen[$processIdOnPort] = $true

        if ($DryRun) {
            Write-Host "[DRY-RUN] Would stop PID $processIdOnPort listening on port $port"
        } else {
            $stopped = Stop-ProcessSafe -ProcessId $processIdOnPort
            if ($stopped) {
                Write-Host "Stopped PID $processIdOnPort listening on port $port"
            } else {
                Write-Warning "Could not stop PID $processIdOnPort on port $port"
            }
        }
    }
}

if (-not $DryRun -and (Test-Path $stateFile)) {
    Remove-Item -Path $stateFile -Force
    Write-Host "Removed state file: $stateFile"
}

Write-Host 'Done.'
