<?php
/**
 * System Stacji Pogodowej - Serwerowy skrypt zapisu prognozy (Cache)
 * 
 * Ten skrypt odbiera dane prognozy z zewnętrznego API pobrane przez ESP32 
 * (lub zapasowy skrypt komputerowy) i zapisuje je do lokalnego pliku JSON.
 * Dzięki temu rozwiązaniu panel użytkownika nie wykonuje obciążających i podatnych
 * na blokady połączeń wychodzących HTTP/HTTPS.
 */

// ZABEZPIECZENIE: Zmień ten klucz na swój własny, silny ciąg znaków w celach produkcyjnych!
define('API_KEY', 'ZASTAP_TO_SWOIM_SEKRETNYM_KLUCZEM_API');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pobranie surowych danych wejściowych JSON
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    // Walidacja obecności klucza API oraz jego poprawności
    if (isset($inputData['api_key']) && $inputData['api_key'] === API_KEY) {
        if (isset($inputData['data'])) {
            // Zapis pakietu danych struktury prognozy do pliku cache JSON
            file_put_contents('prognoza.json', json_encode($inputData['data']));
            echo "Sukces: Prognoza została pomyślnie zapisana na serwerze.";
        } else {
            header("HTTP/1.1 400 Bad Request");
            echo "Błąd: Brak danych prognozy w żądaniu.";
        }
    } else {
        header("HTTP/1.1 401 Unauthorized");
        echo "Błąd: Autoryzacja nieudana. Niepoprawny klucz API Key.";
    }
} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo "Błąd: Metoda niedozwolona. Skrypt akceptuje wyłącznie żądania typu POST.";
}
?>