#include <Wire.h>
#include <Adafruit_BMP280.h>
#include <BH1750.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <Preferences.h>
#include <ElegantOTA.h>

// ====================================================================
// --- SYSTEM WEBSERIAL (BUFOR LOGÓW W PRZEGLĄDARCE) ---
// ====================================================================
String webSerialBuffer = "";
const int maxLogLines = 50;

// Funkcja logująca jednocześnie do fizycznego Seriala oraz bufora WebSerial
void logMessage(String msg) {
  Serial.println(msg); 
  
  int lineCount = 0;
  for (int i = 0; i < webSerialBuffer.length(); i++) {
    if (webSerialBuffer[i] == '\n') lineCount++;
  }
  
  if (lineCount >= maxLogLines) {
    int firstNewLine = webSerialBuffer.indexOf('\n');
    webSerialBuffer = webSerialBuffer.substring(firstNewLine + 1);
  }
  
  webSerialBuffer += msg + "\n";
}

// ====================================================================
// --- KONFIGURACJA SIECIOWO-SERWEROWA (UZUPEŁNIJ PRZED WGRANIEM!) ---
// ====================================================================
Preferences preferences;
WebServer server(80);

String ssid = "";
String password = "";

// UWAGA: Przed wgraniem kodu na mikrokontroler wpisz tutaj swoje dane Wi-Fi.
// Pamiętaj, aby NIE wypychać swoich prawdziwych danych na publiczne repozytorium GitHub!
const char* default_ssid = "YOUR_WIFI_SSID";
const char* default_password = "YOUR_WIFI_PASSWORD";

// Konfiguracja serwera bazy danych oraz klucza uwierzytelniającego skrypt PHP
const char* serverPath = "https://your-domain.com/insert_data.php"; 
const char* apiKey = "YOUR_SECRET_API_KEY";

bool apMode = false;
unsigned long lastWifiCheck = 0;
const unsigned long wifiCheckInterval = 30000;

const char* ap_ssid = "ESP32_Stacja_Meteo";

// ====================================================================
// --- KONFIGURACJA DLA SENSORA PRĘDKOŚCI WIATRU (RS485 - UART2) ---
// ====================================================================
#define RXD2 16      
#define TXD2 17      
#define RE_DE_WIND 4 // Pin sterujący kierunkiem przepływu (konwerter MAX485)

// Zapytanie Modbus RTU o odczyt rejestru prędkości wiatru
const byte requestFrameWindSpeed[] = {0x01, 0x03, 0x00, 0x00, 0x00, 0x01, 0x84, 0x0A};
byte responseFrameWindSpeed[7];

// ====================================================================
// --- KONFIGURACJA DLA SENSORA KIERUNKU WIATRU (RS485 - UART1) ---
// ====================================================================
#define RXD1 18       
#define TXD1 19       
#define RE_DE_DIR 23  // Pin sterujący kierunkiem przepływu (konwerter MAX485)

// Zapytanie Modbus RTU o odczyt rejestru kierunku wiatru
const byte requestFrameWindDirection[] = {0x01, 0x03, 0x00, 0x01, 0x00, 0x01, 0xD5, 0xCA};
byte responseFrameWindDirection[7];

// ====================================================================
// --- KONFIGURACJA SENSORÓW 1-WIRE ORAZ DHT ---
// ====================================================================
#define DHTPIN 15     
#define DHTTYPE DHT22 
DHT dht(DHTPIN, DHTTYPE);

#define ONE_WIRE_BUS 14 
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature ds18b20(&oneWire);

// ====================================================================
// --- INICJALIZACJA CZUJNIKÓW I2C (SDA: 21, SCL: 22) ---
// ====================================================================
Adafruit_BMP280 bmp; 
BH1750 lightMeter;   

bool bmpStatus = false;
bool bh1750Status = false;

// Deklaracje prototypów funkcji przed setup()
float readWindSpeed();
int readWindDirection();
void startAP();
void handleRoot();
void handleSave();
void handleWebSerial();
void handleWebSerialData();
void resetI2CBus();

void setup() {
  Serial.begin(115200);
  delay(500); 
  
  logMessage("\n--- URUCHAMIANIE STACJI METEO Z MONITORINGIEM WEBSERIAL ---");

  // Odczyt zapisanych danych Wi-Fi z pamięci nieulotnej NVS
  preferences.begin("wifi-config", false);
  ssid = preferences.getString("ssid", default_ssid);
  password = preferences.getString("password", default_password);
  preferences.end();

  logMessage("Wczytane SSID: " + ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid.c_str(), password.c_str());
  logMessage("Rozpoczęto łączenie z WiFi...");

  int timeout = 0;
  while (WiFi.status() != WL_CONNECTED && timeout < 16) {
    delay(500);
    Serial.print(".");
    timeout++;
  }
  Serial.println("");

  if (WiFi.status() == WL_CONNECTED) {
    logMessage("Połączono pomyślnie! IP: " + WiFi.localIP().toString());
  } else {
    logMessage("Nie udało się połączyć przy starcie. Uruchamiam AP...");
    startAP();
  }

  // Rejestracja endpointów serwera HTTP (zarządzanie panelem i Wi-Fi)
  server.on("/", handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.on("/webserial", handleWebSerial);
  server.on("/webserial/data", handleWebSerialData);

  // Inicjalizacja biblioteki aktualizacji Over-The-Air (OTA) przez www
  ElegantOTA.begin(&server);    
  server.begin();
  logMessage("Serwer HTTP, ElegantOTA oraz panel WebSerial zostały uruchomione.");

  dht.begin();
  logMessage("Czujnik DHT22 zainicjalizowany na GPIO 15.");

  ds18b20.begin();
  logMessage("Czujnik DS18B20 zainicjalizowany na GPIO 14.");

  Wire.begin();

  // Próba automatycznego wykrycia adresu czujnika ciśnienia BMP280
  if (!bmp.begin(0x76)) {
    logMessage("Nie znaleziono BMP280 pod 0x76, próba 0x77...");
    if (!bmp.begin(0x77)) {
      logMessage("BŁĄD: Nie można odnaleźć czujnika BMP280!");
    } else {
      bmpStatus = true;
    }
  } else {
    bmpStatus = true;
  }

  if (bmpStatus) {
    bmp.setSampling(Adafruit_BMP280::MODE_NORMAL,
                    Adafruit_BMP280::SAMPLING_X2,
                    Adafruit_BMP280::SAMPLING_X16,
                    Adafruit_BMP280::FILTER_X16,
                    Adafruit_BMP280::STANDBY_MS_500);
  }

  if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
    logMessage("Czujnik BH1750 zainicjalizowany poprawnie.");
    bh1750Status = true;
  } else {
    logMessage("BŁĄD: Nie można odnaleźć czujnika BH1750!");
  }
  
  // Konfiguracja pinów kierunku przepływu danych dla transceiverów RS485
  pinMode(RE_DE_WIND, OUTPUT);
  pinMode(RE_DE_DIR, OUTPUT);
  digitalWrite(RE_DE_WIND, LOW); 
  digitalWrite(RE_DE_DIR, LOW);  

  // Inicjalizacja sprzętowych magistral UART dla Modbus RTU
  Serial1.begin(4800, SERIAL_8N1, RXD1, TXD1);
  delay(100);
  
  Serial2.begin(4800, SERIAL_8N1, RXD2, TXD2);
  delay(100);
  
  logMessage("Wszystkie magistrale gotowe. Rozpoczynam pętlę pomiarową...");
  logMessage("-----------------------------------------------------------------");
}

void loop() {
  server.handleClient();
  ElegantOTA.loop(); 

  if (apMode || WiFi.status() != WL_CONNECTED) {
    if (!apMode) startAP();
    delay(10);
    return; 
  }

  // Główny interwał pomiarowy i wysyłka (co 5 sekund)
  static unsigned long lastMeasurementTime = 0;
  if (millis() - lastMeasurementTime >= 5000) {
    lastMeasurementTime = millis();

    logMessage("[WiFi status: Połączono]");

    // --- 1. ODCZYT SENSORÓW RS485 (MODBUS RTU) ---
    float currentSpeed = readWindSpeed();
    delay(200); 
    
    int currentDirection = readWindDirection();
    delay(100); 

    // --- 2. ODCZYT I2C + SYSTEM RATUNKOWY DLA ZAWIESZONEGO SENSORA LUX ---
    float bmpTemp = NAN;
    float bmpPressure = NAN;
    float lux = -1.0;

    if (bh1750Status) {
      lux = lightMeter.readLightLevel();
      if (lux < 0 || lux > 60000.0) {
        logMessage("[ALERT I2C] Wykryto zawieszenie czujnika LUX! Odwieszanie linii I2C...");
        resetI2CBus(); 
        lux = lightMeter.readLightLevel(); 
      }
    }

    if (bmpStatus) {
      bmpTemp = bmp.readTemperature();
      bmpPressure = bmp.readPressure() / 100.0F;
    }

    // --- 3. ODCZYT DHT22 ORAZ 1-WIRE ---
    float dhtHumidity = dht.readHumidity();
    float dhtTemp = dht.readTemperature();

    ds18b20.requestTemperatures(); 
    float dsTemp = ds18b20.getTempCByIndex(0); 

    // --- 4. FORMATOWANIE I LOGOWANIE RAPORTU DIAGNOSTYCZNEGO ---
    logMessage("================= POMIAR METEO =================");
    
    if (currentSpeed >= 0) {
      logMessage("  Prędkość wiatru  : " + String(currentSpeed, 1) + " m/s");
    } else {
      logMessage("  Prędkość wiatru  : BŁĄD ODCZYTU RS485 (Brak odpowiedzi/ramki)");
    }
    
    if (currentDirection >= 0) {
      String dirTxt = "";
      int degrees = currentDirection;
      if (degrees >= 338 || degrees < 23)       dirTxt = "N - Północ";
      else if (degrees >= 23 && degrees < 68)   dirTxt = "NE - Północny-Wschód";
      else if (degrees >= 68 && degrees < 113)  dirTxt = "E - Wschód";
      else if (degrees >= 113 && degrees < 158) dirTxt = "SE - Południowy-Wschód";
      else if (degrees >= 158 && degrees < 203) dirTxt = "S - Południe";
      else if (degrees >= 203 && degrees < 248) dirTxt = "SW - Południowy-Zachód";
      else if (degrees >= 248 && degrees < 293) dirTxt = "W - Zachód";
      else if (degrees >= 293 && degrees < 338) dirTxt = "NW - Północny-Zachód";
      
      logMessage("  Kierunek wiatru  : " + String(currentDirection) + "° (" + dirTxt + ")");
    } else {
      logMessage("  Kierunek wiatru  : BŁĄD ODCZYTU RS485 (Brak odpowiedzi/ramki)");
    }

    if (bmpStatus && !isnan(bmpTemp)) {
      logMessage("  Temp. (BMP280)   : " + String(bmpTemp, 1) + " °C");
      logMessage("  Ciśnienie        : " + String(bmpPressure, 1) + " hPa");
    } else {
      logMessage("  Czujnik BMP280   : BŁĄD MAGISTRALI I2C!");
    }

    if (!isnan(dhtTemp) && !isnan(dhtHumidity)) {
      logMessage("  Temp. (DHT22)    : " + String(dhtTemp, 1) + " °C");
      logMessage("  Wilgotność       : " + String(dhtHumidity, 1) + " %");
    } else {
      logMessage("  Czujnik DHT22    : Błąd odczytu danych!");
    }

    if (dsTemp != DEVICE_DISCONNECTED_C) {
      logMessage("  Temp. (DS18B20)  : " + String(dsTemp, 1) + " °C");
    } else {
      logMessage("  Czujnik DS18B20  : Rozłączony!");
    }

    if (bh1750Status && lux >= 0) {
      logMessage("  Nasłonecznienie  : " + String(lux, 0) + " lx");
    } else {
      logMessage("  Czujnik BH1750   : BŁĄD MAGISTRALI I2C!");
    }
    logMessage("==================================================");

    // --- 5. EKSPORT DANYCH DO ZEWNĘTRZNEJ BAZY SQL PRZEZ HTTP GET ---
    HTTPClient http;
    // Priorytet ma stabilniejszy odczyt z czujnika DHT22, jako zapas służy BMP280
    float mainTemperature = (!isnan(dhtTemp)) ? dhtTemp : bmpTemp;

    String fullUrl = String(serverPath) 
                   + "?api_key="        + String(apiKey)
                   + "&temperature="    + (isnan(mainTemperature) ? "0.0" : String(mainTemperature, 1)) 
                   + "&pressure="       + (isnan(bmpPressure) ? "0.0" : String(bmpPressure, 2)) 
                   + "&humidity="       + (isnan(dhtHumidity) ? "0.0" : String(dhtHumidity, 1)) 
                   + "&lux="            + (lux < 0 ? "0" : String(lux, 0)) 
                   + "&wind_speed="     + (currentSpeed < 0 ? "0.0" : String(currentSpeed, 1)) 
                   + "&wind_direction=" + (currentDirection < 0 ? "0" : String(currentDirection, 0));

    logMessage("HTTP GET -> " + fullUrl);
    
    http.begin(fullUrl);
    int httpResponseCode = http.GET();
    
    if (httpResponseCode > 0) {
      logMessage("Baza SQL -> Odpowiedź serwera: " + String(httpResponseCode));
    } else {
      logMessage("Baza SQL -> BŁĄD TRANSMISJI: " + String(http.errorToString(httpResponseCode).c_str()));
    }
    http.end();
  }
}

// Funkcja ratunkowa: Reset sprzętowy stanu linii SDA/SCL magistrali I2C
void resetI2CBus() {
  Wire.end();
  pinMode(21, OUTPUT);
  pinMode(22, OUTPUT);
  digitalWrite(21, HIGH);
  digitalWrite(22, HIGH);
  delay(10);
  Wire.begin(21, 22);
  lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE);
  if (bmpStatus) bmp.begin(0x76);
}

// Uruchomienie lokalnego punktu dostępowego (tryb konfiguracyjny AP)
void startAP() {
  apMode = true;
  WiFi.mode(WIFI_AP);
  WiFi.softAP(ap_ssid); 
}

// Podstrona główna - formularz zmiany konfiguracji Wi-Fi
void handleRoot() {
  String html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>";
  html += "<title>Konfiguracja Stacji Meteo</title>";
  html += "<style>body{font-family:Arial,sans-serif;margin:40px;background:#222;color:#fff;} .card{background:#333;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.5);max-width:400px;margin:auto;} input[type=text],input[type=password]{width:100%;padding:10px;margin:10px 0;box-sizing:border-box;background:#444;color:#fff;border:1px solid #555;} input[type=submit]{width:100%;background:#007bff;color:white;padding:10px;border:none;border-radius:4px;cursor:pointer;font-weight:bold;} input[type=submit]:hover{background:#0056b3;}</style></head>";
  html += "<body><div class='card'><h2>Konfiguracja Wi-Fi</h2><form action='/save' method='POST'>";
  html += "Nazwa sieci (SSID):<br><input type='text' name='input_ssid' required><br>";
  html += "Hasło:<br><input type='password' name='input_pass'><br><br>";
  html += "<input type='submit' value='Zapisz i Restartuj'></form><br><hr style='border-color:#555;'><br>";
  html += "<div style='text-align:center;'><a href='/webserial' style='color:#28a745; font-weight:bold; text-decoration:none; font-size:18px;'>🖥️ OTWÓRZ KONSOLĘ DIAGNOSTYCZNĄ</a></div>";
  html += "<br><div style='text-align:center;'><a href='/update' style='color:#007bff; text-decoration:none;'>Aktualizacja Oprogramowania (OTA)</a></div></div></body></html>";
  server.send(200, "text/html", html);
}

// Obsługa zapisu nowych danych Wi-Fi do pamięci NVS i restart układu
void handleSave() {
  if (server.hasArg("input_ssid")) {
    preferences.begin("wifi-config", false);
    preferences.putString("ssid", server.arg("input_ssid"));
    preferences.putString("password", server.arg("input_pass"));
    preferences.end();
    server.send(200, "text/html", "Zapisano nową sieć. Restart stacji...");
    delay(2000);
    ESP.restart(); 
  }
}

// Podstrona panelu WebSerial zintegrowanego z dokumentem HTML
void handleWebSerial() {
  String html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>ESP32 WebSerial Monitor</title>";
  html += "<style>body{background-color:#111; color:#0f0; font-family:monospace; padding:20px; font-size:14px;}";
  html += "#console{width:100%; height:80vh; background:#222; border:1px solid #444; padding:10px; overflow-y:scroll; white-space:pre-wrap; border-radius:5px;}";
  html += "h2{color:#fff; font-family:sans-serif;} .btn{background:#28a745; color:white; padding:10px 15px; border:none; border-radius:4px; cursor:pointer; font-weight:bold; text-decoration:none; display:inline-block;}</style>";
  html += "<script>setInterval(function(){ fetch('/webserial/data').then(response => response.text()).then(text => { var div = document.getElementById('console'); div.innerText = text; div.scrollTop = div.scrollHeight; }); }, 1000);</script></head>";
  html += "<body><h2>🖥️ Konsola Diagnostyczna Na Żywo (WebSerial)</h2>";
  html += "<div id='console'>Czekam na logi ze stacji meteo...</div><br><a href='/' class='btn'>⬅ Powrót do menu stacji</a></body></html>";
  server.send(200, "text/html", html);
}

// Zwracanie aktualnego bufora tekstowego dla skryptu AJAX panelu WebSerial
void handleWebSerialData() {
  server.send(200, "text/plain", webSerialBuffer);
}

// Odczyt prędkości wiatru przez zapytanie Modbus RTU i parsowanie ramki odpowiedzi
float readWindSpeed() {
  while(Serial2.available() > 0) Serial2.read();
  digitalWrite(RE_DE_WIND, HIGH); // Przełączenie MAX485 w tryb nadawania
  delay(5);
  Serial2.write(requestFrameWindSpeed, sizeof(requestFrameWindSpeed));
  Serial2.flush();
  delayMicroseconds(150);
  digitalWrite(RE_DE_WIND, LOW);  // Przełączenie MAX485 w tryb odbierania
  
  unsigned long startWait = millis();
  while (Serial2.available() < 7 && (millis() - startWait < 200));

  if (Serial2.available() >= 7) {
    for (int i = 0; i < 7; i++) responseFrameWindSpeed[i] = Serial2.read();
    if (responseFrameWindSpeed[0] == 0x01 && responseFrameWindSpeed[1] == 0x03 && responseFrameWindSpeed[2] == 0x02) {
      return ((responseFrameWindSpeed[3] << 8) | responseFrameWindSpeed[4]) / 10.0;
    }
  }
  return -1.0; 
}

// Odczyt kierunku wiatru w stopniach (0-359) przez zapytanie Modbus RTU
int readWindDirection() {
  while(Serial1.available() > 0) Serial1.read();
  digitalWrite(RE_DE_DIR, HIGH);  // Przełączenie MAX485 w tryb nadawania
  delay(5);
  Serial1.write(requestFrameWindDirection, sizeof(requestFrameWindDirection));
  Serial1.flush();
  delayMicroseconds(150);
  digitalWrite(RE_DE_DIR, LOW);   // Przełączenie MAX485 w tryb odbierania
  
  unsigned long startWait = millis();
  while (Serial1.available() < 7 && (millis() - startWait < 200));

  if (Serial1.available() >= 7) {
    for (int i = 0; i < 7; i++) responseFrameWindDirection[i] = Serial1.read();
    if (responseFrameWindDirection[0] == 0x01 && responseFrameWindDirection[1] == 0x03 && responseFrameWindDirection[2] == 0x02) {
      return (responseFrameWindDirection[3] << 8) | responseFrameWindDirection[4]; 
    }
  }
  return -1; 
}
