
# 🌤️ Inteligentna Stacja Meteo IoT z Autorskim Systemem Awaryjnym (Failover Solution)

## 📌 O Projekcie

Projekt przedstawia w pełni funkcjonalny, zaawansowany system telemetryczny do monitorowania warunków atmosferycznych w czasie rzeczywistym. Architektura systemu obejmuje pełną ścieżkę danych: od fizycznego pomiaru na zróżnicowanych magistralach sprzętowych, przez bezprzewodową transmisję Wi-Fi, aż po bezpieczny serwerowy backend i interaktywny panel użytkownika (Dashboard).

Kluczowym elementem wyróżniającym ten projekt na tle standardowych rozwiązań IoT jest **wdrożenie modułu awaryjnego (Failover Script) w środowisku Node.js**. W przypadku fizycznego uszkodzenia stacji, braku zasilania lub problemów z sieciowym połączeniem bezprzewodowym, system automatycznie (lub po uruchomieniu przez administratora) przełącza się na pobieranie danych ze światowej klasy publicznych interfejsów API dla precyzyjnych współrzędnych stacji. Zapewnia to bezprzerwowość danych historycznych i ciągłość generowania wykresów analitycznych.

---

## 🏗️ Pełna Architektura Systemu (Data Architecture)

Projekt został podzielony na 3 niezależne warstwy, co odzwierciedla zasady czystej architektury oprogramowania (Clean Architecture) i pozwala na łatwe skalowanie kodu:

### A. Warstwa Sprzętowa (Hardware Layer - ESP32)

Sercem fizycznej stacji jest mikrokontroler **ESP32**. Oprogramowanie układowe zostało napisane w języku C++ zoptymalizowanym pod kątem wielozadaniowości obsługi magistral. Mikrokontroler zarządza jednoczesnym odczytem z czujników i co kilka minut buduje pakiet danych, który wysyła zabezpieczonym protokołem HTTP GET bezpośrednio na serwer produkcyjny.

### B. Warstwa Awaryjna (Failover Layer - Node.js)

Gdy sprzęt ulegnie awarii lub straci zasilanie, uruchamiany jest skrypt `pogoda_backup.js` osadzony w środowisku Node.js. Pobiera on dane meteorologiczne z zewnętrznego API dla współrzędnych geograficznych stacji, dokonuje matematycznego przeliczenia jednostek i wysyła zapytanie na serwer, idealnie imitując fizyczne urządzenie i zachowując ciągłość osi czasu w bazie danych.

### C. Warstwa Serwerowa i Prezentacji (Backend & Frontend)

* **Backend (PHP + MySQL):** Odbiera pakiety, filtruje je, waliduje klucz bezpieczeństwa i zapisuje w relacyjnej bazie danych za pomocą bezpiecznych zapytań parametryzowanych.


* **Frontend (Responsive Dashboard):** Panel użytkownika wykonany w nowoczesnym stylu *Dark Premium Design*. Wykorzystuje technologię CSS Grid/Flexbox oraz bibliotekę **Chart.js** do generowania interaktywnych wykresów zmian pogodowych.



---

## 📁 Struktura Katalogów i Wyjaśnienie Roli Plików

W repozytorium zachowano logiczny, modułowy porządek:

```text
stacja-pogodowa-iot/
├── firmware/
│   └── stacja_iot_esp32/
│       └── stacja_iot_esp32.ino   # Firmware C++ sterujący pracą mikrokontrolera ESP32
├── backup-system/
│   ├── pogoda_backup.js           # Skrypt zapasowy (Node.js) pobierający dane z Open-Meteo
│   └── start_backup.bat           # Skrypt wsadowy Windows do błyskawicznego odpalenia backupu
└── web-server/
    ├── .htaccess                  # Zaawansowana konfiguracja serwera Apache (zabezpieczenia)
    ├── index.php                  # Panel główny, wykresy Chart.js, wektorowy kompas i termometr
    ├── insert.php                 # Bezpieczny punkt końcowy API odbierający dane do bazy MySQL
    └── zapisz_prognoze.php        # System lokalnego keszowania danych prognozy pogody

```

### Szczegółowe wyjaśnienie roli plików na serwerze:

* **`insert.php`:** To brama wejściowa dla telemetrii. Skrypt nasłuchuje żądań GET. Zanim cokolwiek trafi do bazy, skrypt sprawdza poprawność unikalnego tokenu `api_key`. Jeśli token jest błędny, serwer odrzuca połączenie.


* **`zapisz_prognoze.php`:** Odpowiada za optymalizację działania aplikacji. Pobieranie prognozy pogody na żywo przy każdym odświeżeniu strony przez użytkownika blokowałoby serwer i generowało niepotrzebny ruch (często prowadząc do tzw. *cURL timeout*). Ten skrypt pobiera prognozę raz na określony czas i zapisuje ją do lokalnego pliku tekstowego jako JSON cache.


* **`.htaccess`:** Pilnuje porządku i bezpieczeństwa na serwerze Apache. Wymusza certyfikat SSL (HTTPS), ukrywa rozszerzenia plików `.php` w adresie URL użytkownika oraz wstrzykuje nagłówki bezpieczeństwa chroniące przed atakami sieciowymi.



---

## 🛠️ Specyfikacja Techniczna i Integracja Sensorów

### 1. Protokoły i Magistrale w Firmware (ESP32)

Fizyczna stacja obsługuje aż trzy całkowicie odmienne standardy przesyłu sygnałów, co wymagało precyzyjnego zarządzania czasem i przerwaniami:

* **Modbus RTU przez RS485:** Wykorzystany do obsługi profesjonalnego anemometru (prędkość wiatru) oraz wiatrowskazu (kierunek wiatru). Komunikacja odbywa się za pomocą wysyłania 8-bajtowych ramek zapytania i odczytu odpowiedzi ze sterowaniem pinami RE/DE na układach konwerterów MAX485 połączonych ze sprzętowymi portami UART.


* **I2C (Inter-Integrated Circuit):** Magistrala obsługująca cyfrowy luksomierz **BH1750** oraz sensor ciśnienia **BMP280**. Zaimplementowano algorytm skanowania adresów, dzięki czemu system sam wykrywa czujnik niezależnie od konfiguracji adresu `0x76` czy `0x77`.


* **OneWire & 1-Wire:** Precyzyjna obsługa czujnika **DHT22** (wilgotność powietrza) oraz **DS18B20** (temperatura gleby lub wody).



### 2. Zaawansowane Funkcje Panelu (Frontend UI)

* **Wektorowy Kompas Kierunku Wiatru:** Zamiast surowego tekstu, na stronie zaimplementowano interaktywną różę wiatrów. Wykorzystuje ona właściwości CSS `transform: rotate()` powiązane z dynamicznymi danymi o kącie wiatru ($0^\circ - 360^\circ$) prosto z sensora Modbus. Prędkość i kierunek wiatru zostały od siebie odizolowane interfejsowo w celu poprawy czytelności UI.


* **Termometr Real-Time:** Słupkowy wskaźnik temperatury płynnie dopasowujący swoją wysokość i kolorystykę w zależności od napływających danych z czujników.


* **Matematyczna Faza Księżyca:** Panel wylicza aktualną fazę księżyca bezpośrednio w kodzie PHP na podstawie daty i długości cyklu synodycznego (29.53 dnia), eliminując potrzebę odpytywania zewnętrznych serwerów astronomicznych.



---

## 🔒 Bezpieczeństwo i Dobre Praktyki (Cybersecurity)

Projekt został zabezpieczony przed najpopularniejszymi zagrożeniami z listy OWASP Top 10:

* **Eliminacja SQL Injection:** W pliku `insert.php` do wprowadzania zmiennych do bazy danych użyto wyłącznie **Prepared Statements** (zapytań parametryzowanych) w interfejsie MySQLi. Surowe dane od użytkownika/stacji nigdy nie są bezpośrednio łączone z zapytaniem SQL.


* **Ochrona przed XX/XSS (Cross-Site Scripting):** Wdrożono restrykcyjną politykę **Content Security Policy (CSP)**. Serwer generuje unikalną, losową wartość (tzw. `nonce`) przy każdym odświeżeniu strony. Przeglądarka uruchomi tylko te skrypty JavaScript, które posiadają ten token, blokując wstrzyknięty, złośliwy kod z zewnętrznych źródeł.


* **Nagłówki Security (.htaccess):**
* `X-Frame-Options: DENY` - Całkowicie blokuje możliwość osadzenia panelu wewnątrz innej strony (ochrona przed atakami typu Clickjacking).


* `X-Content-Type-Options: nosniff` - Wymusza na przeglądarce ścisłe trzymanie się typów plików i blokuje próby wykonania skryptów ukrytych w plikach tekstowych lub obrazach.


* `Strict-Transport-Security (HSTS)` - Wymusza bezpieczne, szyfrowane połączenie przez SSL.





---

## 🚀 Instrukcja Wdrożenia

### 1. Baza Danych i Serwer WWW

1. Utwórz bazę danych MySQL i stwórz strukturę tabeli dla odczytów (temperatura, wilgotność, ciśnienie, lux, prędkość i kierunek wiatru).


2. Wrzuć zawartość folderu `web-server/` na swój hosting przez FTP.


3. Otwórz `insert.php` oraz `index.php` i uzupełnij pola na własne dane logowania bazy oraz zdefiniuj tajny `API_KEY`.



### 2. Konfiguracja Fizycznej Stacji (ESP32)

1. Otwórz `firmware/stacja_iot_esp32/stacja_iot_esp32.ino` w Arduino IDE.


2. Podaj dane do swojego domowego Wi-Fi.


3. Wpisz pełny adres URL kierujący do Twojego pliku `insert.php` na hostingu oraz wpisz zdefiniowany wcześniej `apiKey`.


4. Wgraj program na płytkę.



### 3. Obsługa Awaryjna (Node.js)

1. Inicjalizuj środowisko Node.js na komputerze pełniącym rolę zapasową.
2. W przypadku problemów ze stacją przejdź do folderu `backup-system/` i otwórz plik `pogoda_backup.js`, podając adres swojej domeny oraz poprawny klucz API.


3. Uruchom skrót `start_backup.bat`. Program zacznie pobierać dane synoptyczne dla regionu i automatycznie uzupełni bazę danych, utrzymując ciągłość działania wykresów.
