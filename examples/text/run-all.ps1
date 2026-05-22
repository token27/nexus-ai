# ---------------------------------------------------------------------------
# Runs all text examples in this folder with friendly status output.
#
# What this script does:
# 1) Loads environment variables from .env (root first, then local folder)
# 2) Checks required vars per example
# 3) Runs each PHP example
# 4) Classifies result as OK / FAIL / SKIPPED
# ---------------------------------------------------------------------------

param(
    # Optional custom .env path. If omitted, default candidates are used.
    [string]$EnvFile = ""
)

$ErrorActionPreference = "Stop"

# Prints a visual header section in console output.
function Write-Section([string]$title) {
    Write-Host ""
    Write-Host "=== $title ==="
}

# Minimal .env parser for automation use in PowerShell.
function Load-DotEnvFile([string]$path) {
    if (-not (Test-Path -LiteralPath $path)) {
        return $false
    }

    Write-Host "Loading env file: $path"
    Get-Content -LiteralPath $path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq "" -or $line.StartsWith("#")) {
            return
        }

        $parts = $line -split "=", 2
        if ($parts.Count -ne 2) {
            return
        }

        $key = $parts[0].Trim()
        $value = $parts[1].Trim()

        if (
            ($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))
        ) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        # Set only for current process/session.
        [Environment]::SetEnvironmentVariable($key, $value, "Process")
    }

    return $true
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Resolve-Path (Join-Path $scriptDir "..\..")

$envCandidates = @()
if ($EnvFile -ne "") {
    if ([System.IO.Path]::IsPathRooted($EnvFile)) {
        $envCandidates += $EnvFile
    } else {
        $envCandidates += (Join-Path $repoRoot $EnvFile)
    }
} else {
    # Default search order:
    # 1) repo root .env
    # 2) local folder .env
    $envCandidates += (Join-Path $repoRoot ".env")
    $envCandidates += (Join-Path $scriptDir ".env")
}

$loadedEnv = $false
foreach ($candidate in $envCandidates) {
    if (Load-DotEnvFile $candidate) {
        $loadedEnv = $true
        break
    }
}

if (-not $loadedEnv) {
    Write-Host "No .env file loaded. Using current process environment variables."
}

# Define examples and their required env vars.
$examples = @(
    @{ File = "01-openai.php"; Required = @("OPENAI_API_KEY") },
    @{ File = "02-anthropic.php"; Required = @("ANTHROPIC_API_KEY") },
    @{ File = "03-gemini.php"; Required = @("GEMINI_API_KEY") },
    @{ File = "04-deepseek.php"; Required = @("DEEPSEEK_API_KEY") },
    @{ File = "05-ollama.php"; Required = @() },
    @{ File = "06-xai.php"; Required = @("XAI_API_KEY") },
    @{ File = "07-custom-injected-provider.php"; Required = @() }
)

$ok = 0
$fail = 0
$skip = 0

# Execute each example one by one.
foreach ($example in $examples) {
    $file = $example.File
    $required = $example.Required

    $missing = @()
    foreach ($key in $required) {
        $value = [Environment]::GetEnvironmentVariable($key, "Process")
        if ([string]::IsNullOrWhiteSpace($value)) {
            $missing += $key
        }
    }

    Write-Section $file

    if ($missing.Count -gt 0) {
        Write-Host ("SKIPPED (missing env): " + ($missing -join ", "))
        $skip++
        continue
    }

    # Capture output so we can detect user-friendly "Error:" messages,
    # even when script exits with code 0.
    $output = & php (Join-Path $scriptDir $file) 2>&1
    $output | ForEach-Object { Write-Host $_ }

    $printedError = $false
    foreach ($line in $output) {
        if (($line.ToString()).TrimStart().StartsWith("Error:")) {
            $printedError = $true
            break
        }
    }

    # Successful if:
    # - process exit code is 0
    # - script did not print "Error: ..."
    if ($LASTEXITCODE -eq 0 -and -not $printedError) {
        Write-Host "STATUS: OK"
        $ok++
    } else {
        if ($printedError -and $LASTEXITCODE -eq 0) {
            Write-Host "STATUS: FAIL (example reported runtime error)"
        } else {
            Write-Host "STATUS: FAIL (exit code $LASTEXITCODE)"
        }
        $fail++
    }
}

Write-Section "Summary"
Write-Host "OK:      $ok"
Write-Host "FAIL:    $fail"
Write-Host "SKIPPED: $skip"

if ($fail -gt 0) {
    # Return non-zero to help CI/automation detect failures.
    exit 1
}

exit 0
