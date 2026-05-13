# Bird CMS one-line installer for Windows (PowerShell 5.1+).
#
# Usage (default: clones to .\bird-cms, runs on port 8080):
#   iwr -UseBasicParsing https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.ps1 | iex
#
# Customize via env vars:
#   $env:BIRD_CMS_DIR = "mysite"; $env:BIRD_CMS_PORT = "8088"
#   iwr -UseBasicParsing .../install.ps1 | iex
#
# What this script does:
#   1. Verify Docker is installed and running.
#   2. Fetch the repo (git clone, or zip fallback if git is missing).
#   3. docker compose up -d.
#   4. Wait for /health to answer 200.
#   5. Open the install wizard in your default browser.

$ErrorActionPreference = "Stop"

$Repo           = if ($env:BIRD_CMS_REPO)    { $env:BIRD_CMS_REPO }    else { "https://gitlab.com/codimcc/bird-cms.git" }
$Branch         = if ($env:BIRD_CMS_BRANCH)  { $env:BIRD_CMS_BRANCH }  else { "main" }
$Dir            = if ($env:BIRD_CMS_DIR)     { $env:BIRD_CMS_DIR }     else { "./bird-cms" }
$PortRequested  = $env:BIRD_CMS_PORT
$PortRangeStart = if ($env:BIRD_CMS_PORT_RANGE_START) { [int]$env:BIRD_CMS_PORT_RANGE_START } else { 8080 }
$PortRangeEnd   = if ($env:BIRD_CMS_PORT_RANGE_END)   { [int]$env:BIRD_CMS_PORT_RANGE_END }   else { 8099 }
$Timeout        = if ($env:BIRD_CMS_HEALTH_TIMEOUT)   { [int]$env:BIRD_CMS_HEALTH_TIMEOUT }   else { 60 }

function Say  ($m) { Write-Host "==> $m" -ForegroundColor Cyan }
function Warn ($m) { Write-Host "!!  $m" -ForegroundColor Yellow }
function Fail ($m) { Write-Host "xx  $m" -ForegroundColor Red; exit 1 }

# Return $true when a process is listening on the port, $false otherwise.
function Port-InUse([int]$p) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect("127.0.0.1", $p, $null, $null)
        if ($async.AsyncWaitHandle.WaitOne(200, $false)) {
            $client.EndConnect($async)
            return $true
        } else {
            return $false
        }
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

# Returns the first free port in [$start..$end], or $null if none.
function First-FreePort([int]$start, [int]$end) {
    for ($p = $start; $p -le $end; $p++) {
        if (-not (Port-InUse $p)) { return $p }
    }
    return $null
}

# 1. Docker present?
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Fail @"
Docker is required. Install Docker Desktop first:
    https://docs.docker.com/desktop/install/windows-install/
Then re-run this command from a fresh PowerShell window.
"@
}

# 2. Docker running?
docker info 1>$null 2>$null
if ($LASTEXITCODE -ne 0) {
    Fail "Docker is installed but not running. Start Docker Desktop and re-run."
}

# 3. Compose detection.
docker compose version 1>$null 2>$null
if ($LASTEXITCODE -eq 0) {
    $Compose = "docker compose"
} elseif (Get-Command docker-compose -ErrorAction SilentlyContinue) {
    $Compose = "docker-compose"
} else {
    Fail "Docker Compose plugin not found. Reinstall Docker Desktop (it bundles compose)."
}

# 4. Target dir empty?
if (Test-Path $Dir) {
    Fail "Path '$Dir' already exists. Move or rename it, or set `$env:BIRD_CMS_DIR='newpath'."
}

# 4.5. Pick a port. Honor BIRD_CMS_PORT when set; otherwise scan the range
# starting at 8080 and pick the first free slot.
if ($PortRequested) {
    $Port = $PortRequested
    if (Port-InUse ([int]$Port)) {
        Fail "Requested port $Port is already in use. Pick another, or unset BIRD_CMS_PORT to auto-select."
    }
} else {
    $picked = First-FreePort $PortRangeStart $PortRangeEnd
    if (-not $picked) {
        Fail "No free port in $PortRangeStart..$PortRangeEnd. Free one up or set `$env:BIRD_CMS_PORT=N."
    }
    $Port = "$picked"
    if ([int]$Port -ne $PortRangeStart) {
        Say "Port $PortRangeStart is busy -- auto-selected free port $Port."
    }
}

Say "Fetching Bird CMS into $Dir ..."
if (Get-Command git -ErrorAction SilentlyContinue) {
    git clone --branch $Branch --depth 1 $Repo $Dir 1>$null
} else {
    Warn "git not found. Falling back to zip download."
    $cleanRepo = $Repo -replace '\.git$', ''
    $zipUrl = "$cleanRepo/-/archive/$Branch/bird-cms-$Branch.zip"
    $tmpZip = [System.IO.Path]::Combine($env:TEMP, "bird-cms-$Branch.zip")
    Invoke-WebRequest -UseBasicParsing -Uri $zipUrl -OutFile $tmpZip
    $parent = Split-Path -Parent $Dir
    if (-not $parent) { $parent = "." }
    Expand-Archive -Path $tmpZip -DestinationPath $parent -Force
    Move-Item -Path (Join-Path $parent "bird-cms-$Branch") -Destination $Dir
    Remove-Item $tmpZip
}

Set-Location $Dir

# 5. Port override (also pick a unique container name).
# Compose merges 'ports:' lists additively, so use the !override tag
# (compose-spec v2.21+) to fully replace the parent's port map.
if ($Port -ne "8080") {
    Say "Port $Port requested -- writing docker-compose.override.yml"
    @"
services:
  bird-cms:
    container_name: bird-cms-$Port
    ports: !override
      - "${Port}:80"
"@ | Out-File -Encoding utf8 docker-compose.override.yml
}

Say "Starting container ..."
& cmd /c "$Compose up -d" 1>$null

Say "Waiting for Bird CMS to come up (up to ${Timeout}s) ..."
$deadline = (Get-Date).AddSeconds($Timeout)
while ($true) {
    try {
        $r = Invoke-WebRequest -UseBasicParsing -Uri "http://localhost:$Port/health" -TimeoutSec 2
        if ($r.StatusCode -eq 200) { break }
    } catch { }
    if ((Get-Date) -ge $deadline) {
        Warn "Health check did not pass within ${Timeout}s."
        Warn "Inspect logs:  cd $Dir; $Compose logs --tail 100 bird-cms"
        break
    }
    Start-Sleep -Seconds 1
}

$url = "http://localhost:$Port/install"
Start-Process $url

Write-Host ""
Write-Host "Bird CMS is starting up." -ForegroundColor Green
Write-Host ""
Write-Host "  Install wizard:  $url"
Write-Host "  Project dir:     $Dir"
Write-Host ""
Write-Host "Useful commands:"
Write-Host "  cd $Dir; $Compose logs -f bird-cms     # follow logs"
Write-Host "  cd $Dir; $Compose restart              # restart"
Write-Host "  cd $Dir; $Compose down                 # stop and remove"
Write-Host ""
Write-Host "Walk the wizard, pick 'seed demo content', and you'll have a"
Write-Host "working site with three sample articles in under a minute."
