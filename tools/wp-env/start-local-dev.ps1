param(
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$themeDir = Join-Path $repoRoot 'packages\theme'
$builderDir = Join-Path $repoRoot 'packages\builder-plugin'

function Test-LocalHttpOk {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -TimeoutSec 5 -UseBasicParsing
        return $response.StatusCode -ge 200 -and $response.StatusCode -lt 400
    } catch {
        return $false
    }
}

function Ensure-ViteTerminal {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name,
        [Parameter(Mandatory = $true)]
        [string]$WorkingDirectory,
        [Parameter(Mandatory = $true)]
        [int]$Port,
        [Parameter(Mandatory = $true)]
        [string]$TestUrl,
        [Parameter(Mandatory = $true)]
        [string]$Command
    )

    if (Test-LocalHttpOk $TestUrl) {
        Write-Host "$Name already responding on 127.0.0.1:$Port"
        return
    }

    $encodedCommand = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($Command))
    Start-Process pwsh -WorkingDirectory $WorkingDirectory -ArgumentList @(
        '-NoExit',
        '-EncodedCommand',
        $encodedCommand
    ) | Out-Null

    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if (Test-LocalHttpOk $TestUrl) {
            Write-Host "$Name ready on 127.0.0.1:$Port"
            return
        }

        Start-Sleep -Milliseconds 500
    }

    throw "$Name did not start on 127.0.0.1:$Port"
}

function Get-WslIp {
    $addresses = (wsl -u root hostname -I).Trim() -split '\s+'
    return $addresses | Select-Object -First 1
}

function Invoke-ElevatedPowerShellFile {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Content
    )

    $scriptPath = Join-Path $env:TEMP ('blocky-local-proxy-' + [guid]::NewGuid().ToString() + '.ps1')
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

function Restart-IpHelperService {
    $script = @"
Restart-Service iphlpsvc -Force
"@

    Invoke-ElevatedPowerShellFile -Content $script
}

function Ensure-LocalApacheProxy {
    param(
        [Parameter(Mandatory = $true)]
        [string]$WslIp
    )

    $script = @"
netsh interface portproxy delete v4tov4 listenaddress=127.0.0.1 listenport=8888 2>`$null
netsh interface portproxy add v4tov4 listenaddress=127.0.0.1 listenport=8888 connectaddress=$WslIp connectport=8888
"@

    Invoke-ElevatedPowerShellFile -Content $script
}

Write-Host 'Starting Apache in WSL...'
wsl --shutdown
wsl -u root bash -lc "sed -i 's#http://localhost:5173#http://127.0.0.1:5173#; s#http://localhost:5174#http://127.0.0.1:5174#' /var/www/blocky/wp-config.php"
wsl -u root wp --path=/var/www/blocky db query "UPDATE wp_options SET option_value='http://127.0.0.1:8888' WHERE option_name IN ('home','siteurl');" --allow-root | Out-Null
wsl -u root python3 -c "from pathlib import Path; ports = Path('/etc/apache2/ports.conf'); ports.write_text(ports.read_text().replace('Listen 8888', 'Listen 0.0.0.0:8888')); blocky = Path('/etc/apache2/sites-available/blocky.conf'); blocky.write_text(blocky.read_text().replace('<VirtualHost *:8888>', '<VirtualHost 0.0.0.0:8888>')); enabled = Path('/etc/apache2/sites-enabled/blocky.conf'); enabled.write_text(enabled.read_text().replace('<VirtualHost *:8888>', '<VirtualHost 0.0.0.0:8888>')) if enabled.exists() else None"
wsl -u root bash -lc "systemctl restart apache2"

$wslIp = Get-WslIp
$wslUrl = "http://$wslIp:8888/"

if (-not [string]::IsNullOrWhiteSpace($wslIp)) {
    Write-Host "Configuring localhost proxy to WSL at $wslIp:8888..."
    Ensure-LocalApacheProxy -WslIp $wslIp

    if (-not (Test-LocalHttpOk 'http://127.0.0.1:8888/')) {
        Write-Host 'Restarting Windows IP Helper service to recover localhost proxy...'
        Restart-IpHelperService
        Ensure-LocalApacheProxy -WslIp $wslIp
    }
}

if (-not (Test-LocalHttpOk 'http://127.0.0.1:8888/')) {
    throw 'Apache did not become reachable on http://127.0.0.1:8888/'
}

Ensure-ViteTerminal -Name 'Theme Vite' -WorkingDirectory $themeDir -Port 5173 -TestUrl 'http://127.0.0.1:5173/assets/js/theme.ts' -Command 'npx vite --host 127.0.0.1'
Ensure-ViteTerminal -Name 'Builder Vite' -WorkingDirectory $builderDir -Port 5174 -TestUrl 'http://127.0.0.1:5174/src/main.tsx' -Command 'npx vite --host 127.0.0.1'

Write-Host ''
Write-Host 'Local dev stack is ready:'
Write-Host '  http://127.0.0.1:8888/'
Write-Host '  http://127.0.0.1:5173/'
Write-Host '  http://127.0.0.1:5174/'

if (-not $NoBrowser) {
    Start-Process 'http://127.0.0.1:8888/' | Out-Null
}