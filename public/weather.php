<?php 

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; 


// Open-Meteo multi-city weather viewer wrapped into a single $content variable
// Timezone fix: format hourly times using the city's timezone directly (no UTC conversion)

// Supported cities: Cologne, Berlin, Lisbon, Los Angeles, New York City
$cities = [
    'cologne'     => ['name' => 'Cologne',       'lat' => 50.9375,  'lon' => 6.9603,  'tz' => 'Europe/Berlin'],
    'berlin'      => ['name' => 'Berlin',        'lat' => 52.52,    'lon' => 13.405,  'tz' => 'Europe/Berlin'],
    'lisbon'      => ['name' => 'Lisbon',        'lat' => 38.7223,   'lon' => -9.1393, 'tz' => 'Europe/Lisbon'],
    'losangeles'  => ['name' => 'Los Angeles',   'lat' => 34.0522,   'lon' => -118.2437,'tz' => 'America/Los_Angeles'],
    'newyork'     => ['name' => 'New York City', 'lat' => 40.7128,   'lon' => -74.0060, 'tz' => 'America/New_York'],
];

// Pick city from GET param, default to Cologne
$cityKey = 'cologne';
if (isset($_GET['city']) && array_key_exists($_GET['city'], $cities)) {
    $cityKey = $_GET['city'];
}
$city = $cities[$cityKey];
$lat  = $city['lat'];
$lon  = $city['lon'];
$tz   = $city['tz'];
$cityName = $city['name'];

// Cache per city
$cacheFile = sys_get_temp_dir() . '/openmeteo_' . strtolower($cityKey) . '.json';
$cacheTtl  = 300; // 5 minutes

// Try cache
$useCache = false;
$data = null;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cached = file_get_contents($cacheFile);
    $tmp = json_decode($cached, true);
    if ($tmp !== null) {
        $data = $tmp;
        $useCache = true;
    }
}

// Fetch if no valid cache
if (!$useCache) {
    $params = [
        'latitude'        => $lat,
        'longitude'       => $lon,
        'hourly'          => 'temperature_2m,precipitation_probability',
        'current_weather' => 'true',
        'timezone'        => $tz
    ];
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'OpenMeteoDemo/1.0');
    $body = curl_exec($ch);
    curl_close($ch);

    if ($body !== false) {
        $data = json_decode($body, true);
        if ($data !== null) {
            file_put_contents($cacheFile, $body);
        }
    }
}

// Weather code to description
function describeWeatherCode($code) {
    if ($code === null) return 'Unknown';
    if ($code === 0) return 'Clear sky';
    if ($code === 1) return 'Mainly clear';
    if ($code === 2) return 'Partly cloudy';
    if ($code === 3) return 'Overcast';
    if (in_array($code, [45, 48])) return 'Fog';
    if (in_array($code, range(51, 67))) return 'Rain';
    if (in_array($code, [80, 81, 82])) return 'Rain showers';
    if (in_array($code, [95, 96, 99])) return 'Thunderstorm';
    return 'Weather';
}

// Prepare display data
$currentTemp = null;
$currentWinds = null;
$currentCode  = null;
$currentDesc  = 'Unknown';
if ($data && !empty($data['current_weather'])) {
    $cur = $data['current_weather'];
    $currentTemp = $cur['temperature'] ?? null;
    $currentWinds = $cur['windspeed'] ?? null;
    $currentCode  = $cur['weathercode'] ?? null;
    $currentDesc  = describeWeatherCode($currentCode);
}

// Hourly data (next 12 hours)
$hourly = $data['hourly'] ?? [];
$times = $hourly['time'] ?? [];
$temps = $hourly['temperature_2m'] ?? [];
$pp    = $hourly['precipitation_probability'] ?? [];

$hoursToShow = 12;
$hourRows = [];
for ($i = 0; $i < $hoursToShow; $i++) {
    if (!isset($times[$i])) break;
    $tIso = $times[$i];
    // Use the city's timezone directly to label the hour
    $dt = new DateTime($tIso, new DateTimeZone($tz));
    $label = $dt->format('H:i');

    $temp = $temps[$i] ?? '';
    $prob = $pp[$i] ?? '';

    $hourRows[] = [
        'time' => $label,
        'temp' => is_numeric($temp) ? round($temp) : $temp,
        'prob' => is_numeric($prob) ? (int)$prob : $prob
    ];
}

// Build HTML into $content
$content = '';

// HTML header and CSS
$content .= '<style>';
$content .= 'body { font-family: Arial, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; color: #333; }';
$content .= 'h1 { font-size: 1.6em; margin-bottom: 0.5em; }';
$content .= '.card { border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: #fff; }';
$content .= '.row { display: flex; gap: 1rem; align-items: baseline; }';
$content .= '.big { font-size: 2.2em; font-weight: bold; }';
$content .= '.muted { color: #666; }';
$content .= 'table { width: 100%; border-collapse: collapse; }';
$content .= 'th, td { padding: 6px 8px; border-bottom: 1px solid #eee; text-align: left; }';
$content .= 'th { color: #555; font-weight: normal; font-size: 0.9em; }';
$content .= '.bar { height: 8px; background: #e6f0ff; border-radius: 4px; overflow: hidden; }';
$content .= '.bar > span { display: block; height: 100%; background: #4a90e2; }';
$content .= '</style>';



// City picker header
$content .= '<h1>' . htmlspecialchars($cityName) . ' Weather — Open-Meteo (no API key)</h1>';
$content .= '<form method="get" style="margin: 0 0 12px 0;">';
$content .= '<label for="city" style="margin-right:6px;">City:</label>';
$content .= '<select name="city" id="city" onchange="this.form.submit()">';

foreach ($cities as $key => $info) {
    $selected = ($cityKey === $key) ? ' selected' : '';
    $content .= '<option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($info['name']) . '</option>';
}
$content .= '</select>';
$content .= '<noscript><button type="submit">Show weather</button></noscript>';
$content .= '</form>';

// Current and meta info
$content .= '<div class="card">';
$content .= '<div class="row">';
$content .= '<div class="big" style="margin-right: 12px;">' . htmlspecialchars($currentTemp !== null ? round($currentTemp) : '—') . '°C</div>';
$content .= '<div>';
$content .= '<div><strong>Current:</strong> ' . htmlspecialchars($currentDesc) . '</div>';
if ($currentWinds !== null) {
    $content .= '<div class="muted">Winds: ' . htmlspecialchars(round($currentWinds, 1)) . ' m/s</div>';
}
$content .= '</div></div>';
$content .= '<div class="muted" style="margin-top:6px;">';
$content .= 'City coordinates: ' . htmlspecialchars($lat) . ', ' . htmlspecialchars($lon) . ' • Open-Meteo forecast (no key)';
$content .= '</div>';
$content .= '</div>';

// Hourly forecast
$content .= '<div class="card">';
$content .= '<h2 style="margin:0 0 8px 0;">Next ' . $hoursToShow . ' hours forecast</h2>';
$content .= '<table aria-label="Hourly forecast"><thead><tr><th>Time (' . htmlspecialchars($cityName) . ')</th><th>Temp °C</th><th>Precip. probability %</th></tr></thead><tbody>';
foreach ($hourRows as $row) {
    $content .= '<tr>';
    $content .= '<td>' . htmlspecialchars($row['time']) . '</td>';
    $content .= '<td>' . htmlspecialchars($row['temp']) . '</td>';
    $content .= '<td>';
    $content .= '<div class="bar" aria-label="Precipitation probability" title="' . htmlspecialchars($row['prob']) . '%">';
    $content .= '<span style="width: ' . (is_numeric($row['prob']) ? (int)$row['prob'] : 0) . '%;"></span>';
    $content .= '</div> ' . htmlspecialchars($row['prob']) . '%';
    $content .= '</td>';
    $content .= '</tr>';
}
if (empty($hourRows)) {
    $content .= '<tr><td colspan="3" class="muted">No hourly data available.</td></tr>';
}
$content .= '</tbody></table>';
$content .= '</div>';

// Footer
$content .= '<footer class="muted" style="font-size:0.85em; color:#666;">';
$content .= 'Data source: Open-Meteo (no API key required). Times shown in the city timezone.';
$content .= '</footer>';


// $content now contains the full HTML. Your template system can render it as needed.
// Example: echo $content; or pass to your framework's rendering pipeline.














// Render the chat UI
$appContent = '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script><div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;>
    <div style="position: absolute;">
        <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
        &#x1F5C3;
    </a>
    <h2 style="margin: 0; padding 0 0 20px 0; position: absolute; top: 10px; left: 50px;">Weather Report</h2>

    </div>

</div>
<div style="margin: 0; padding: 0;"> <br />  </div>
    <div style="margin: 0 20px 80px 20px;">  ' . $content . '</div>';

$spw = \App\Core\SpwBase::getInstance();
$spw->renderLayout($appContent, "Weather Report");






