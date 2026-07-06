/**
 * System Stacji Pogodowej - Tryb Awaryjny (Failover Script)
 * 
 * UWAGA ARCHITEKTONICZNA:
 * Skrypt ten stanowi zapasowe źródło danych (Fallback / Backup Solution).
 * W architekturze produkcyjnej system polega na fizycznym mikrokontrolerze ESP32.
 * W przypadku awarii sprzętowej, utraty zasilania lub łączności Wi-Fi stacji IoT,
 * niniejszy skrypt uruchamiany na maszynie lokalnej przejmuje rolę ESP32, 
 * pobierając dane z publicznego API dla współrzędnych geograficznych stacji i wysyłając
 * je na serwer, zapewniając ciągłość historyczną analizowanych trendów.
 */

// Konfiguracja punktu końcowego API oraz autoryzacji (Zanonimizowano)
const SERVER_API_URL = "https://TWOJA_DOMENA.PL/insert.php"; 
const API_KEY        = "ZASTAP_TO_SWOIM_SEKRETNYM_KLUCZEM_API";

// Współrzędne stacji bazowej (Kędzierzyn-Koźle)
const LAT = "50.35";
const LON = "18.22";

// URL darmowego API Open-Meteo uzupełniającego brakujące pomiary z czujników stacji
const WEATHER_API_URL = `https://api.open-meteo.com/v1/forecast?latitude=${LAT}&longitude=${LON}&current=temperature_2m,relative_humidity_2m,surface_pressure,wind_speed_10m,wind_direction_10m,shortwave_radiation`;

async function uruchomTrybAwaryjny() {
    try {
        console.log("[FAILOVER] Inicjalizacja procedury zapasowej...");
        console.log(`[FAILOVER] Pobieranie aktualnej lokalnej pogody dla współrzędnych: LAT=${LAT}, LON=${LON}...`);
        
        const weatherResponse = await fetch(WEATHER_API_URL);
        if (!weatherResponse.ok) {
            throw new Error(`Błąd zewnętrznego API pogodowego: ${weatherResponse.status}`);
        }
        
        const weatherData = await weatherResponse.json();
        const aktualna = weatherData.current;

        // Przeliczenie W/m² na jednostki Lux (1 W/m² ~ 126 lx dla naturalnego widma światła dziennego)
        const wyliczoneLuxy = Math.round(aktualna.shortwave_radiation * 126);

        console.log(`-> Pobrano pomyślnie! Temp: ${aktualna.temperature_2m}°C, Ciśnienie: ${aktualna.surface_pressure} hPa, Oświetlenie: ${wyliczoneLuxy} lx`);

        // Mapowanie parametrów API na strukturę akceptowaną przez system serwerowy bazy danych
        const parametryDoWyslania = {
            api_key: API_KEY,
            temperature: aktualna.temperature_2m,
            pressure: aktualna.surface_pressure,
            humidity: aktualna.relative_humidity_2m,
            lux: wyliczoneLuxy, 
            wind_speed: (aktualna.wind_speed_10m / 3.6).toFixed(1), // Konwersja jednostek: km/h -> m/s
            wind_direction: aktualna.wind_direction_10m
        };

        // Generowanie parametrów GET do zapytania HTTP
        const urlParams = new URLSearchParams(parametryDoWyslania).toString();
        const pelnyUrlHosting = `${SERVER_API_URL}?${urlParams}`;

        console.log("[FAILOVER] Synchronizacja i przesyłanie danych ratunkowych na serwer główny...");
        
        const hostingResponse = await fetch(pelnyUrlHosting);
        if (!hostingResponse.ok) {
            throw new Error(`Błąd serwera bazy danych! Status HTTP: ${hostingResponse.status}`);
        }

        const odpowiedzPHP = await hostingResponse.text();
        
        console.log("=== ODPOWIEDŹ Z SERWERA PRODUKCYJNEGO ===");
        console.log(odpowiedzPHP);
        console.log("=========================================");

    } catch (error) {
        console.error("[CRITICAL] Błąd procedury awaryjnej:", error.message);
    }
}

// Uruchomienie skryptu zapasowego
uruchomTrybAwaryjny();