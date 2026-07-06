<?php
/**
 * System Stacji Pogodowej - Serwerowy skrypt odbioru danych pomiarowych (API)
 * 
 * Odbiera dane telemetryczne wysyłane przez mikrokontroler ESP32 (lub zapasowy skrypt PC),
 * weryfikuje token bezpieczeństwa i zapisuje rekordy do relacyjnej bazy danych MySQL.
 */

// ZABEZPIECZENIE: Musi być identyczny z kluczem w pozostałych skryptach systemu
define("API_KEY", "ZASTAP_TO_SWOIM_SEKRETNYM_KLUCZEM_API");

// Sprawdzenie obecności i poprawności klucza autoryzacyjnego w parametrach GET
if (!isset($_GET["api_key"]) || $_GET["api_key"] !== API_KEY) {
    http_response_code(403);
    die("Błąd: Brak autoryzacji. Niepoprawny lub brakujący API Key.");
}

// Sprawdzenie czy przesłano podstawowe, wymagane parametry meteorologiczne
if (isset($_GET["temperature"]) && isset($_GET["pressure"])) {
   
    // Przypisanie danych wejściowych z walidacją ( fallback do null w przypadku braku opcjonalnych czujników )
    $temperature    = $_GET["temperature"];
    $pressure       = $_GET["pressure"];
    $humidity       = isset($_GET["humidity"]) ? $_GET["humidity"] : null;
    $lux            = isset($_GET["lux"]) ? $_GET["lux"] : null;
    $wind_speed     = isset($_GET["wind_speed"]) ? $_GET["wind_speed"] : null;
    $wind_direction = isset($_GET["wind_direction"]) ? $_GET["wind_direction"] : null;

    // Kredencjały połączenia z bazą danych (Zanonimizowane do portfolio)
    $servername    = "localhost";
    $username      = "NAZWA_UZYTKOWNIKA_BAZY";
    $password      = "HASLO_DO_BAZY_DANYCH";
    $database_name = "NAZWA_BAZY_STACJI_METEO";

    $connection = new mysqli($servername, $username, $password, $database_name);
    if ($connection->connect_error) {
        http_response_code(500);
        die("MySQL Connection Failed: " . $connection->connect_error);
    }

    // Przygotowanie bezpiecznego zapytania SQL (Prepared Statements) przeciwko SQL Injection
    $sql = "INSERT INTO pomiary (temperatura, cisnienie, wilgotnosc, naswietlenie, wiatr_predkosc, wiatr_kierunek) 
            VALUES (?, ?, ?, ?, ?, ?)";
   
    $stmt = $connection->prepare($sql);
   
    if ($stmt) {
        // Powiązanie zmiennych z zapytaniem: d = double, i = integer
        $stmt->bind_param("dddddi", $temperature, $pressure, $humidity, $lux, $wind_speed, $wind_direction);
      
        if ($stmt->execute() === TRUE) {
            echo "Sukces: Nowy rekord został pomyślnie dodany do bazy.";
        } else {
            http_response_code(500);
            echo "Błąd podczas wykonywania zapytania SQL: " . $stmt->error;
        }
      
        $stmt->close();
    } else {
        http_response_code(500);
        echo "Błąd przygotowania zapytania SQL (Prepare Statement): " . $connection->error;
    }

    $connection->close();
} else {
    http_response_code(400);
    echo "Błąd: Parametry 'temperature' oraz 'pressure' są wymagane do poprawnego zapisu.";
}
?>