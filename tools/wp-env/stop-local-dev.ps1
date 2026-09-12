$ErrorActionPreference = 'Stop'

function Invoke-ElevatedPowerShellFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Content
    )

    $scriptPath = Join-Path $env:TEMP ('blocky-local-proxy-stop-' + [guid]::NewGuid().ToString() + '.ps1')
    Set-Content -Path $scriptPath -Value $Content -Encoding ASCII

    try {
        Start-Process powershell -Verb RunAs -ArgumentList @(
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-File', $scriptPath
        ) -Wait | Out-Null
    } finally {
        Remove-Item $scriptPath -ErrorAction SilentlyContinue
    }
}

function Stop-PortProcess {
    param(
        [Parameter(Mandatory = $true)]
        [int]$Port
    )

    $connections = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $Port -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique

    foreach ($processId in $connections) {
        try {
            Stop-Process -Id $processId -Force -ErrorAction Stop
            Write-Host "Stopped PID $processId on port $Port"
        } catch {
            Write-Host (("Failed to stop PID {0} on port {1}: {2}" -f $processId, $Port, $_.Exception.Message))
        }
    }
}

Stop-PortProcess -Port 5173
Stop-PortProcess -Port 5174

Invoke-ElevatedPowerShellFile -Content 'netsh interface portproxy delete v4tov4 listenaddress=127.0.0.1 listenport=8888 2>$null'

wsl -u root bash -lc 'systemctl stop apache2 || true' | Out-Null

Write-Host 'Stopped local Blocky dev services.'