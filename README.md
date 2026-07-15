
# 🌤️ Inteligentna Stacja Meteo IoT z Autorskim Systemem Awaryjnym (Failover Solution)

[https://parkowa.info.pl/](https://parkowa.info.pl/)

<img width="1536" height="2048" alt="image" src="https://github.com/user-attachments/assets/379d445e-f528-43af-a1d6-08db01f298ee" />
<img width="1536" height="2048" alt="WhatsApp Image 2026-07-14 at 23 41 03" src="https://github.com/user-attachments/assets/46a25224-3cbb-4db9-a912-30b503e0625b" />
<img width="1536" height="2048" alt="image" src="https://github.com/user-attachments/assets/f0f2334f-059e-4324-839a-c30141203166" />
<img width="1536" height="2048" alt="image" src="https://github.com/user-attachments/assets/a48c9b82-67b5-467f-94cf-4f1a950f07ed" />

## 📌 O Projekcie

Projekt przedstawia w pełni funkcjonalny, zaawansowany system telemetryczny do monitorowania warunków atmosferycznych w czasie rzeczywistym. Architektura systemu obejmuje pełną ścieżkę danych: od fizycznego pomiaru na zróżnicowanych magistralach sprzętowych, przez bezprzewodową transmisję Wi-Fi, aż po bezpieczny serwerowy backend i interaktywny panel użytkownika (Dashboard).

Kluczowym elementem wyróżniającym ten projekt na tle standardowych rozwiązań IoT jest **wdrożenie bezobsługowego serwerowego modułu awaryjnego (Smart Failover Script) w PHP**. W przypadku fizycznego uszkodzenia stacji, braku zasilania lub problemów z sieciowym połączeniem bezprzewodowym, serwer automatycznie – poprzez harmonogram zadań CRON – wykrywa brak aktywności mikrokontrolera. Jeśli stacja milczy, skrypt samoczynnie przełącza się na pobieranie danych meteorologicznych z publicznych interfejsów API dla precyzyjnych współrzędnych stacji. Zapewnia to bezprzerwowość danych historycznych i ciągłość generowania wykresów analitycznych bez potrzeby ingerencji człowieka.

---

## 🏗️ Pełna Architektura Systemu (Data Architecture)

Projekt został podzielony na niezależne warstwy, co odzwierciedla zasady czystej architektury oprogramowania (Clean Architecture) i pozwala na łatwe skalowanie kodu:

### A. Warstwa Sprzętowa (Hardware Layer - ESP32)

Sercem fizycznej stacji jest mikrokontroler **ESP32**. Oprogramowanie układowe zostało napisane w języku C++ zoptymalizowanym pod kątem wielozadaniowości obsługi magistral oraz nowoczesnych funkcji zdalnego zarządzania urządzeniem (OTA / WebServer). Mikrokontroler zarządza jednoczesnym odczytem z czujników i co kilka minut buduje pakiet danych, który wysyła zabezpieczonym protokołem HTTP GET bezpośrednio na serwer produkcyjny.

### B. Warstwa Awaryjna (Smart Failover Layer - Cloud PHP)

Gdy sprzęt ulegnie awarii lub straci zasilanie, serwerowy harmonogram zadań (CRON) uruchamia skrypt `pogoda_backup.php`. Skrypt weryfikuje czas ostatniego wpisu w bazie danych. Jeśli czas ten przekracza bezpieczny próg (15 minut), skrypt pobiera dane z zewnętrznego API dla współrzędnych geograficznych stacji, dokonuje matematycznego przeliczenia jednostek (np. W/m² na Lux) i poprzez bezpośrednie dołączanie plików (`include`) symuluje żądanie sprzętowe, dbając o ciągłość osi czasu w bazie danych oraz aktualizację cache prognozy.

### C. Warstwa Serwerowa i Prezentacji (Backend & Frontend)

* **Backend (PHP + MySQL):** Odbiera pakiety z ESP32 lub skryptu awaryjnego, filtruje je, waliduje klucz bezpieczeństwa i zapisuje w relacyjnej bazie danych za pomocą bezpiecznych zapytań parametryzowanych (PDO).
* **Frontend (Responsive Dashboard):** Panel użytkownika wykonany w nowoczesnym stylu *Dark Premium Design*. Wykorzystuje technologię CSS Grid/Flexbox oraz bibliotekę **Chart.js** do generowania interaktywnych wykresów zmian pogodowych.

---

## 🌐 Otwarte API dla Szkół i Celów Edukacyjnych

Ważnym filarem projektu jest jego **funkcja społeczno-edukacyjna**. Projekt udostępnia otwarte, w pełni darmowe publiczne API, które pozwala uczniom, studentom oraz lokalnym pasjonatom programowania na pobieranie żywych, rzeczywistych parametrów ze stacji meteorologicznej.

API eliminuje barierę wejścia dla początkujących programistów — nie muszą oni posiadać własnego sprzętu ani konfigurować baz danych, aby uczyć się pracy z formatem JSON, asynchronicznością w JavaScript (funkcja `fetch`), czy wizualizacją danych na wykresach.

### Endpoint API:

`https://parkowa.info.pl/api.php`

* **Metoda:** `GET`
* **Zaimplementowany mechanizm CORS:** Nagłówek `Access-Control-Allow-Origin: *` pozwala uczniom na bezpieczne odpytywanie API bezpośrednio z lokalnych maszyn (`localhost`).
* **Format odpowiedzi:** `application/json` (zwracane liczby są konwertowane na natywne typy `float` dla ułatwienia operacji matematycznych).

### Parametry zapytania:

* `limit` (opcjonalny, domyślnie `1`, maksymalnie `100` ze względów wydajnościowych) – określa liczbę ostatnich rekordów historycznych, które chcemy pobrać.

### Przykłady wywołań:

1. **Najnowszy odczyt** (zwraca pojedynczy, łatwy w parsowaniu obiekt JSON):
`https://parkowa.info.pl/api.php`
2. **Historia ostatnich 10 pomiarów** (zwraca tablicę obiektów):
`https://parkowa.info.pl/api.php?limit=10`

---

## 📁 Struktura Katalogów i Wyjaśnienie Roli Plików

W repozytorium zachowano logiczny, modułowy porządek:

```text
stacja-pogodowa-iot/
├── firmware/
│   └── stacja_iot_esp32/
│       └── stacja_iot_esp32.ino   # Firmware C++ sterujący pracą mikrokontrolera ESP32 (OTA + WebServer)
├── przyklady/
│   └── test_pogoda.html          # Szablon startowy HTML/JS pokazujący uczniom jak odebrać dane z API
└── web-server/
    ├── .htaccess                  # Zaawansowana konfiguracja serwera Apache (zabezpieczenia)
    ├── api.php                    # Otwarte, bezpieczne API edukacyjne dla szkół
    ├── index.php                  # Panel główny, wykresy Chart.js, wektorowy kompas i termometr
    ├── insert_data.php            # Bezpieczny punkt końcowy API odbierający dane do bazy MySQL
    ├── zapisz_prognoze.php        # System lokalnego keszowania danych prognozy pogody
    └── pogoda_backup.php          # Serwerowy skrypt awaryjny (Smart Failover) uruchamiany przez CRON

```

### Szczegółowe wyjaśnienie roli kluczowych plików:

* **`api.php`:** Publiczny punkt dostępu do odczytów dla celów edukacyjnych. Pobiera z bazy najświeższe dane i przetwarza je na czytelny format JSON. Posiada zabezpieczenie blokujące uruchomienie skryptu bez konfiguracji danych dostępowych do bazy danych.
* **`przyklady/test_pogoda.html`:** Gotowy szablon startowy (boilerplate) dedykowany dla uczniów. Pokazuje w przejrzysty sposób, jak w zaledwie kilku linijkach kodu JavaScript połączyć się z endpointem API i dynamicznie wyświetlić dane na własnej stronie www.
* **`insert_data.php`:** To brama wejściowa dla telemetrii. Skrypt nasłuchuje żądań GET. Zanim cokolwiek trafi do bazy, skrypt sprawdza poprawność unikalnego tokenu `api_key`. Jeśli token jest błędny, serwer odrzuca połączenie.
* **`zapisz_prognoze.php`:** Odpowiada za optymalizację działania aplikacji. Pobieranie prognozy pogody na żywo przy każdym odświeżeniu strony przez użytkownika blokowałoby serwer i generowało niepotrzebny ruch. Ten skrypt przetwarza dane synoptyczne i zapisuje je do lokalnego pliku tekstowego jako JSON cache.
* **`pogoda_backup.php`:** Autonomiczny moduł ratunkowy. Sprawdza status stacji i w razie awarii pobiera dane pogodowe z Open-Meteo, po czym bezpiecznie przekazuje je do `insert_data.php` oraz `zapisz_prognoze.php` w celu zachowania spójności bazy oraz wyglądu interfejsu.
* **`.htaccess`:** Pilnuje porządku i bezpieczeństwa na serwerze Apache. Wymusza certyfikat SSL (HTTPS), ukrywa rozszerzenia plików `.php` w adresie URL użytkownika oraz wstrzykuje nagłówki bezpieczeństwa chroniące przed atakami sieciowymi.

---

## 🛠️ Specyfikacja Techniczna i Integracja Sensorów

### 1. Protokoły i Magistrale w Firmware (ESP32)

Fizyczna stacja obsługuje aż trzy całkowicie odmienne standardy przesyłu sygnałów, co wymagało precyzyjnego zarządzania czasem i przerwaniami:

* **Modbus RTU przez RS485:** Wykorzystany do obsługi profesjonalnego anemometru (prędkość wiatru) oraz wiatrowskazu (kierunek wiatru). Komunikacja odbywa się za pomocą wysyłania 8-bajtowych ramek zapytania i odczytu odpowiedzi ze sterowaniem pinami RE/DE na układach konwerterów MAX485 połączonych ze sprzętowymi portami UART.
* **I2C (Inter-Integrated Circuit):** Magistrala obsługująca cyfrowy luksomierz **BH1750** oraz sensor ciśnienia **BMP280**. Zaimplementowano algorytm skanowania adresów, dzięki czemu system sam wykrywa czujnik niezależnie od konfiguracji adresu `0x76` czy `0x77`.
* **OneWire & 1-Wire:** Precyzyjna obsługa czujnika **DHT22** (wilgotność powietrza) oraz **DS18B20** (temperatura gleby lub wody).

### 2. Zaawansowane Funkcje Przemysłowe i Zarządzanie Urządzeniem

W oprogramowaniu układowym wdrożono standardy znane z profesjonalnych systemów komercyjnych:

* **Zdalna Aktualizacja Softu (ElegantOTA):** Stacja umożliwia bezprzewodowe wgrywanie nowego oprogramowania układowego (Over-The-Air) przez sieć Wi-Fi pod adresem `/update`, bez konieczności fizycznego demontażu urządzenia i podłączania kabla USB.
* **Pamięć Nieulotna (Preferences):** Dane uwierzytelniające sieci bezprzewodowej Wi-Fi są dynamicznie zapisywane i odczytywane z pamięci Flash mikrokontrolera przy użyciu biblioteki `Preferences`. Zapobiega to utracie konfiguracji po nagłym zaniku zasilania i pozwala na elastyczną zmianę sieci bez ponownego flashowania kodu.
* **Lokalny Serwer HTTP & Konsola WebSerial:** ESP32 uruchamia własny serwer na porcie 80. Zaimplementowano autorski bufor logów systemowych (`webSerialBuffer`), który agreguje ostatnie linie zdarzeń i pozwala na bezprzewodowy podgląd pracy i debugowanie stacji bezpośrednio z poziomu przeglądarki WWW.

### 3. Zaawansowane Funkcje Panelu (Frontend UI)

* **Wektorowy Kompas Kierunku Wiatru:** Zamiast surowego tekstu, na stronie zaimplementowano interaktywną różę wiatrów. Wykorzystuje ona właściwości CSS `transform: rotate()` powiązane z dynamicznymi danymi o kącie wiatru (0° - 360°) prosto z sensora Modbus. Prędkość i kierunek wiatru zostały od siebie odizolowane interfejsowo w celu poprawy czytelności UI.
* **Termometr Real-Time:** Słupkowy wskaźnik temperatury płynnie dopasowujący swoją wysokość i kolorystykę w zależności od napływających danych z czujników.
* **Matematyczna Faza Księżyca:** Panel wylicza aktualną fazę księżyca bezpośrednio w kodzie PHP na podstawie daty i długości cyklu synodycznego (29.53 dnia), eliminując potrzebę odpytywania zewnętrznych serwerów astronomicznych.

---

## 🔒 Bezpieczeństwo i Dobre Praktyki (Cybersecurity)

Projekt został zabezpieczony przed najpopularniejszymi zagrożeniami z listy OWASP Top 10:

* **Eliminacja SQL Injection:** Do komunikacji z bazą danych wykorzystano interfejs **PDO (Prepared Statements)** z zapytaniami parametryzowanymi. Surowe dane wejściowe nigdy nie są bezpośrednio łączone z zapytaniem SQL.
* **Ochrona przed XSS (Cross-Site Scripting):** Wdrożono restrykcyjną politykę **Content Security Policy (CSP)**. Serwer generuje unikalną, losową wartość (tzw. `nonce`) przy każdym odświeżeniu strony. Przeglądarka uruchomi tylko te skrypty JavaScript, które posiadają ten token, blokując wstrzyknięty, złośliwy kod z zewnętrznych źródeł.
* **Nagłówki Security (.htaccess):**
* `X-Frame-Options: DENY` - Całkowicie blokuje możliwość osadzenia panelu wewnątrz innej strony (ochrona przed atakami typu Clickjacking).
* `X-Content-Type-Options: nosniff` - Wymusza na przeglądarce ścisłe trzymanie się typów plików i blokuje próby wykonania skryptów ukrytych w plikach tekstowych lub obrazach.
* `Strict-Transport-Security (HSTS)` - Wymusza bezpieczne, szyfrowane połączenie przez SSL.



---

## 🚀 Instrukcja Wdrożenia

### 1. Baza Danych i Serwer WWW

1. Utwórz bazę danych MySQL i stwórz strukturę tabeli dla odczytów (temperatura, wilgotność, ciśnienie, lux, prędkość i kierunek wiatru).
2. Wrzuć zawartość folderu `web-server/` na swój hosting przez FTP.
3. Otwórz plik `api.php` bezpośrednio na swoim serwerze i uzupełnij zmienne `$user`, `$password` oraz `$dbname` własnymi, rzeczywistymi danymi do bazy. **Ważne:** Na GitHubie zostaw te pola puste lub wypełnione tekstami pomocniczymi w celu zachowania cyberbezpieczeństwa.

### 2. Konfiguracja Fizycznej Stacji (ESP32)

1. Otwórz `firmware/stacja_iot_esp32/stacja_iot_esp32.ino` w Arduino IDE.
2. Podaj domyślne dane do swojego Wi-Fi oraz pełny adres URL kierujący do Twojego pliku `insert_data.php` na hostingu wraz ze zdefiniowanym kluczem `apiKey`.
3. Wgraj program na płytkę. Po uruchomieniu stacji panel konfiguracyjny sieci oraz konsola logów będą dostępne pod lokalnym adresem IP urządzenia, a panel aktualizacji OTA pod adresem IP urządzenia `/update`.

### 3. Konfiguracja Harmonogramu CRON (Automatyczny Backup)

Aby system awaryjny działał w pełni autonomicznie w chmurze, należy skonfigurować zadanie CRON w panelu hostingu (np. cPanel / DirectAdmin):

* **Zalecany interwał:** Co 15 minut (`*/15 * * * *`)
* **Przykładowa komenda (CLI PHP):**

```bash
/usr/local/bin/php /home/user/public_html/web-server/pogoda_backup.php

```
