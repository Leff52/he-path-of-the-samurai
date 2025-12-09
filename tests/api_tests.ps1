$BaseUrl = "http://localhost:8080"
$RustUrl = "http://localhost:8081"

$TestResults = @()
$PassCount = 0
$FailCount = 0

function Test-Endpoint {
    param(
        [string]$Name,
        [string]$Url,
        [string]$Method = "GET",
        [int]$ExpectedStatus = 200,
        [scriptblock]$Validate = $null
    )
    
    $result = @{
        Name = $Name
        Url = $Url
        Status = "FAIL"
        Message = ""
        Duration = 0
    }
    
    try {
        $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
        $response = Invoke-WebRequest -Uri $Url -Method $Method -UseBasicParsing -TimeoutSec 30
        $stopwatch.Stop()
        $result.Duration = $stopwatch.ElapsedMilliseconds
        
        if ($response.StatusCode -eq $ExpectedStatus) {
            if ($Validate) {
                $content = $response.Content | ConvertFrom-Json
                $validationResult = & $Validate $content
                if ($validationResult -eq $true) {
                    $result.Status = "PASS"
                    $result.Message = "OK ($($result.Duration)ms)"
                } else {
                    $result.Message = "Validation failed: $validationResult"
                }
            } else {
                $result.Status = "PASS"
                $result.Message = "OK ($($result.Duration)ms)"
            }
        } else {
            $result.Message = "Expected $ExpectedStatus, got $($response.StatusCode)"
        }
    } catch {
        $result.Message = $_.Exception.Message
    }
    
    return $result
}



Write-Host "[1/20] Тест PHP Health Page..." -NoNewline
$test = Test-Endpoint -Name "PHP Health Page" -Url "$BaseUrl/health"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[2/20] Тест Database Health..." -NoNewline
$test = Test-Endpoint -Name "Database Health" -Url "$BaseUrl/api/health/db" -Validate {
    param($json)
    return $json.ok -eq $true
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[3/20] Тест Rust API Health..." -NoNewline
$test = Test-Endpoint -Name "Rust Health" -Url "$RustUrl/health" -Validate {
    param($json)
    return $json.ok -eq $true
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[4/20] Тест Dashboard Page..." -NoNewline
$test = Test-Endpoint -Name "Dashboard Page" -Url "$BaseUrl/dashboard"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[5/20] Тест ISS Latest API..." -NoNewline
$test = Test-Endpoint -Name "ISS Latest" -Url "$BaseUrl/api/iss/latest" -Validate {
    param($json)
    return ($json.ok -eq $true) -and ($null -ne $json.data)
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[6/20] Тест ISS Trend API..." -NoNewline
$test = Test-Endpoint -Name "ISS Trend" -Url "$BaseUrl/api/iss/trend?hours=2" -Validate {
    param($json)
    return $json.ok -eq $true
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}


Write-Host "[7/20] Тест ISS Page..." -NoNewline
$test = Test-Endpoint -Name "ISS Page" -Url "$BaseUrl/iss"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[8/20] Тест OSDR List API..." -NoNewline
$test = Test-Endpoint -Name "OSDR List" -Url "$RustUrl/api/osdr" -Validate {
    param($json)
    return ($json.ok -eq $true) -and ($json.data.items.Count -gt 0)
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[9/20] Тест OSDR Page..." -NoNewline
$test = Test-Endpoint -Name "OSDR Page" -Url "$BaseUrl/osdr"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[10/20] Тест JWST Feed API..." -NoNewline
$test = Test-Endpoint -Name "JWST Feed" -Url "$BaseUrl/api/jwst/feed?perPage=5" -Validate {
    param($json)
    return $null -ne $json.items
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[11/20] Тест Astronomy Events API..." -NoNewline
$astroEventsUrl = $BaseUrl + "/api/astro/events?lat=55.7558" + [char]38 + "lon=37.6176" + [char]38 + "days=365"
$test = Test-Endpoint -Name "Astro Events" -Url $astroEventsUrl
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[12/20] Тест Astronomy Positions API..." -NoNewline
$astroPosUrl = $BaseUrl + "/api/astro/positions?lat=55.7558" + [char]38 + "lon=37.6176" + [char]38 + "days=1"
$test = Test-Endpoint -Name "Astro Positions" -Url $astroPosUrl -Validate {
    param($json)
    return $null -ne $json.data
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[13/20] Тест NEO Page..." -NoNewline
$test = Test-Endpoint -Name "NEO Page" -Url "$BaseUrl/neo"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[14/20] Тест APOD Page..." -NoNewline
$test = Test-Endpoint -Name "APOD Page" -Url "$BaseUrl/apod"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[15/20] Тест DONKI Page..." -NoNewline
$test = Test-Endpoint -Name "DONKI Page" -Url "$BaseUrl/donki"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[16/20] Тест SpaceX Page..." -NoNewline
$test = Test-Endpoint -Name "SpaceX Page" -Url "$BaseUrl/spacex"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[17/20] Тест Rust ISS Direct..." -NoNewline
$test = Test-Endpoint -Name "Rust ISS Direct" -Url "$RustUrl/api/iss/latest" -Validate {
    param($json)
    return $json.ok -eq $true
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[18/20] Тест Rust OSDR Pagination..." -NoNewline
$osdrPagUrl = "$RustUrl/api/osdr?limit=5" + [char]38 + "offset=0"
$test = Test-Endpoint -Name "Rust OSDR Pagination" -Url $osdrPagUrl -Validate {
    param($json)
    return ($json.ok -eq $true) -and ($json.data.total -gt 0)
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[19/20] Тест NASA API Health..." -NoNewline
$test = Test-Endpoint -Name "NASA Health" -Url "$BaseUrl/api/health/nasa" -Validate {
    param($json)
    return $null -ne $json.ok
}
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

Write-Host "[20/20] Тест Home Redirect..." -NoNewline
$test = Test-Endpoint -Name "Home Redirect" -Url "$BaseUrl/"
$TestResults += $test
if ($test.Status -eq "PASS") { 
    Write-Host " PASS" -ForegroundColor Green
    $PassCount++
} else { 
    Write-Host " FAIL: $($test.Message)" -ForegroundColor Red
    $FailCount++
}

$TestResults | ForEach-Object {
    $statusColor = if ($_.Status -eq "PASS") { "Green" } else { "Red" }
    $statusIcon = if ($_.Status -eq "PASS") { "[OK]" } else { "[X]" }
    Write-Host "  $statusIcon " -NoNewline -ForegroundColor $statusColor
    Write-Host "$($_.Name.PadRight(30))" -NoNewline
    Write-Host " $($_.Message)" -ForegroundColor $statusColor
}


Write-Host "  Total: " -NoNewline
Write-Host "$PassCount PASSED" -NoNewline -ForegroundColor Green
Write-Host " / " -NoNewline
if ($FailCount -eq 0) {
    Write-Host "$FailCount FAILED" -ForegroundColor Green
} else {
    Write-Host "$FailCount FAILED" -ForegroundColor Red
}
Write-Host ""

$successRate = [math]::Round(($PassCount / ($PassCount + $FailCount)) * 100, 1)
if ($FailCount -eq 0) {
    Write-Host "  Success Rate: $successRate%" -ForegroundColor Green
} elseif ($FailCount -lt 5) {
    Write-Host "  Success Rate: $successRate%" -ForegroundColor Yellow
} else {
    Write-Host "  Success Rate: $successRate%" -ForegroundColor Red
}
Write-Host ""

if ($FailCount -eq 0) {
    Write-Host "  All tests passed! KosmoStars is fully operational." -ForegroundColor Green
} elseif ($FailCount -lt 5) {
    Write-Host "  Some tests failed. Check the services above." -ForegroundColor Yellow
} else {
    Write-Host "  Multiple failures detected. Services may be down." -ForegroundColor Red
}

Write-Host ""
