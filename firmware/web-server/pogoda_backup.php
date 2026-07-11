<?php
/**
 * System Stacji Pogodowej - Inteligentny Tryb Awaryjny (Smart Failover)
 * Wersja z bezpośrednim dołączaniem plików (Inclusion) oraz korektą czasu bazy (+1h).
 */

header('Content-Type: text/plain; charset=utf-8');

// ==========================================
// KONFIGURACJA SYSTEMU (UZUPEŁNIJ SWOJE DANE)
// ==========================================
define('API_KEY', 'YOUR_SECRET_API_KEY_HERE'); // Klucz z insert_data.php i zapisz_prognoze.php

// Kredencjały do bazy danych (zmień na swoje na hostingu, nie podawaj ich na GitHubie)
$db_host = 'localhost';
$db_user = 'YOUR_DB_USER';
$db_pass = 'YOUR_DB_PASSWORD';
$db_name = 'YOUR_DB_NAME';

// Maksymalny czas bez odczytu z czujników (900 sekund = 15 minut)
define('MAX_OFFLINE_TIME', 900); 

// Współrzędne geograficzne stacji (Kędzierzyn-Koźle)
define('LAT', '50.35');
define('LON', '18.22');

// ==========================================
// 1. SPRAWDZANIE CZY ESP32 DZIAŁA (STAN BAZY)
// ==========================================
try {
    echo "[CHECK] Łączenie z bazą danych w celu weryfikacji aktywności ESP32...\n";
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Pobieramy czas ostatniego wpisu DODAJĄC 1 GODZINĘ korekty dla strefy czasowej bazy
    $stmt = $pdo->query("SELECT DATE_ADD(data_pomiaru, INTERVAL 1 HOUR) as data_korekta FROM pomiary ORDER BY data_pomiaru DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $last_timestamp = strtotime($row['data_korekta']);
        $current_timestamp = time();
        $diff = $current_timestamp - $last_timestamp;

        echo "-> Ostatni prawidłowy pomiar z ESP32 (po korekcie czasu): " . date('Y-m-d H:i:s', $last_timestamp) . "\n";
        echo "-> Stacja milczy od: " . $diff . " sekund.\n";

        if ($diff < MAX_OFFLINE_TIME) {
            echo "[OK] ESP32 działa prawidłowo. Zamykanie procedury awaryjnej.\n";
            exit;
        }
    } else {
        echo "[WARN] Brak jakichkolwiek wpisów w bazie. Uruchamianie pierwszego zasilenia...\n";
    }

} catch (PDOException $e) {
    die("[CRITICAL] Błąd bazy danych przy sprawdzaniu statusu: " . $e->getMessage() . "\n");
}

// ==========================================
// 2. URUCHOMIENIE TRYBU AWARYJNEGO (FAILOVER)
// ==========================================
echo "[FAILOVER] Wykryto awarię łączności stacji! Pobieranie danych ratunkowych...\n";

// URL do pełnych danych: bieżących oraz dobowych (dla prognozy)
$weather_api_url = "https://api.open-meteo.com/v1/forecast?latitude=" . LAT . "&longitude=" . LON . "&current=temperature_2m,relative_humidity_2m,surface_pressure,wind_speed_10m,wind_direction_10m,shortwave_radiation&daily=weather_code,temperature_2m_max,temperature_2m_min,sunrise,sunset&timezone=Europe%2FWarsaw";

$context = stream_context_create(["http" => ["timeout" => 10, "header" => "User-Agent: WeatherStationFailover/1.0\r\n"]]);
$apiResponse = @file_get_contents($weather_api_url, false, $context);

if ($apiResponse === false) {
    die("[CRITICAL] Nie można pobrać danych z Open-Meteo.\n");
}

$weatherData = json_decode($apiResponse, true);
if (!isset($weatherData['current']) || !isset($weatherData['daily'])) {
    die("[CRITICAL] Niepoprawny format danych z Open-Meteo.\n");
}

// Przetwarzanie pomiarów bieżących
$current = $weatherData['current'];
$wyliczoneLuxy = round($current['shortwave_radiation'] * 126); // W/m² -> lx
$wind_speed_ms = round($current['wind_speed_10m'] / 3.6, 1);  // km/h -> m/s

// ==========================================
// 3. ZAPIS DO BAZY POPRZEZ INCLUDE
// ==========================================
echo "[FAILOVER] Synchronizacja i przesyłanie pomiarów do insert_data.php...\n";

// Symulujemy parametry GET, które normalnie wysyła mikrokontroler
$_GET['api_key'] = API_KEY;
$_GET['temperature'] = $current['temperature_2m'];
$_GET['pressure'] = $current['surface_pressure'];
$_GET['humidity'] = $current['relative_humidity_2m'];
$_GET['lux'] = $wyliczoneLuxy;
$_GET['wind_speed'] = $wind_speed_ms;
$_GET['wind_direction'] = $current['wind_direction_10m'];

echo "=== ODPOWIEDŹ Z INSERT_DATA ===\n";
ob_start();
include __DIR__ . '/insert_data.php';
$insertOutput = ob_get_clean();
echo trim($insertOutput) . "\n";
echo "===============================\n";

// ==========================================
// 4. AKTUALIZACJA CACHE PROGNOZY
// ==========================================
echo "[FAILOVER] Aktualizacja lokalnego cache prognozy pogody poprzez zapisz_prognoze.php...\n";

// Czyścimy GET i ustawiamy POST dla skryptu prognozy, przekazując pobrane dane i klucz
$_GET = []; 
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['api_key'] = API_KEY;
$_POST['forecast_data'] = json_encode($weatherData['daily']); 

echo "=== ODPOWIEDŹ Z ZAPISZ_PROGNOZE ===\n";
ob_start();
include __DIR__ . '/zapisz_prognoze.php';
$forecastOutput = ob_get_clean();
echo trim($forecastOutput) . "\n";
echo "==================================\n";

echo "[SUCCESS] Procedura awaryjna zakończona pomyślnie.\n";
?>
