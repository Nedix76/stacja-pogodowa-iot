/**
 * System Stacji Pogodowej - Oprogramowanie Układowe Mikrokontrolera (Firmware)
 * 
 * Główny moduł telemetryczny IoT zarządzający pobieraniem danych z sensorów fizycznych:
 * - Prędkość i kierunek wiatru (RS485 / Modbus RTU)
 * - Ciśnienie atmosferyczne (BMP280 I2C)
 * - Wilgotność i temperatura powietrza (DHT22)
 * - Temperatura dodatkowa / gleby (DS18B20 OneWire)
 * - Poziom natężenia oświetlenia (BH1750 I2C)
 */

#include <Wire.h>
#include <Adafruit_BMP280.h>
#include <BH1750.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>

// ====================================================================
// --- KONFIGURACJA SIECIOWE i API ---
// ====================================================================
const char* ssid       = "NAZWA_TWOJEJ_SIECI_WIFI";
const char* password   = "HASLO_DO_WIFI";
const char* serverPath = "https://TWOJA_DOMENA.PL/insert.php"; 
const char* apiKey     = "ZASTAP_TO_SWOIM_SEKRETNYM_KLUCZEM_API";

// ====================================================================
// --- SENSORY RS485 (MODBUS RTU) ---
// ====================================================================
// Konwerter 1: Prędkość wiatru
#define RXD2 16      
#define TXD2 17      
#define RE_DE_WIND 4 

const byte requestFrameWindSpeed[] = {0x01, 0x03, 0x00, 0x00, 0x00, 0x01, 0x84, 0x0A};
byte responseFrameWindSpeed[7];

// Konwerter 2: Kierunek wiatru
#define RXD1 18       
#define TXD1 19       
#define RE_DE_DIR 23  

const byte requestFrameWindDirection[] = {0x01, 0x03, 0x00, 0x01, 0x00, 0x01, 0xD5, 0xCA};
byte responseFrameWindDirection[7];

// ====================================================================
// --- SENSOR DHT22 ---
// ====================================================================
#define DHTPIN 15     
#define DHTTYPE DHT22 
DHT dht(DHTPIN, DHTTYPE);

// ====================================================================
// --- SENSOR DS18B20 (OneWire) ---
// ====================================================================
#define ONE_WIRE_BUS 14 
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature ds18b20(&oneWire);

// ====================================================================
// --- CZUJNIKI I2C ---
// ====================================================================
Adafruit_BMP280 bmp; 
BH1750 lightMeter;   

bool bmpStatus = false;
bool bh1750Status = false;

// Deklaracje funkcji pomocniczych
float readWindSpeed();
int readWindDirection();
void printDirectionText(int degrees);

void setup() {
  Serial.begin(115200);
  delay(500); 
  
  Serial.println("\n--- URUCHAMIANIE ZINTEGROWANEJ STACJI METEO IOT ---");

  // Inicjalizacja komunikacji bezprzewodowej WiFi (w tle)
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  Serial.println("Rozpoczęto procedurę nawiązywania połączenia z siecią WiFi...");

  dht.begin();
  ds18b20.begin();
  Wire.begin(); // Domyślne piny I2C dla ESP32: SDA=21, SCL=22

  // Weryfikacja obecności modułu BMP280
  if (!bmp.begin(0x76)) {
    if (!bmp.begin(0x77)) {
      Serial.println("[ERROR] Nie odnaleziono czujnika ciśnienia BMP280!");
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

  // Weryfikacja obecności modułu luksomierza BH1750
  if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
    bh1750Status = true;
  } else {
    Serial.println("[ERROR] Nie odnaleziono czujnika natężenia światła BH1750!");
  }
  
  // Konfiguracja kierunkowości linii sterujących konwerterów RS485 (Transmit/Receive)
  pinMode(RE_DE_WIND, OUTPUT);
  pinMode(RE_DE_DIR, OUTPUT);
  digitalWrite(RE_DE_WIND, LOW); 
  digitalWrite(RE_DE_DIR, LOW);  

  // Konfiguracja sprzętowych interfejsów UART dla protokołu Modbus
  Serial1.begin(4800, SERIAL_8N1, RXD1, TXD1);
  delay(100);
  Serial2.begin(4800, SERIAL_8N1, RXD2, TXD2);
  delay(100);
  
  Serial.println("Wszystkie magistrale pomiarowe aktywne. Rozpoczynam pętlę główną.");
  Serial.println("-----------------------------------------------------------------");
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[WiFi STATUS: Połączono, Adres IP: "); Serial.print(WiFi.localIP()); Serial.println("]");
  } else {
    Serial.println("[WiFi STATUS: Rozłączono - próba automatycznego nawiązania sesji w tle...]");
  }

  // --- 1. KOMUNIKACJA MODBUS RTU (RS485) ---
  float currentSpeed = readWindSpeed();
  delay(50); 
  int currentDirection = readWindDirection();
  delay(50);

  // --- 2. ODCZYTY MAGISTRALI I2C ---
  float bmpTemp = NAN;
  float bmpPressure = NAN;
  float lux = -1.0;

  if (bmpStatus) {
    bmpTemp = bmp.readTemperature();
    bmpPressure = bmp.readPressure() / 100.0F;
  }
  if (bh1750Status) {
    lux = lightMeter.readLightLevel();
  }

  // --- 3. ODCZYT SENSORA DHT22 ---
  float dhtHumidity = dht.readHumidity();
  float dhtTemp = dht.readTemperature();

  // --- 4. ODCZYT SENSORA DS18B20 ---
  ds18b20.requestTemperatures(); 
  float dsTemp = ds18b20.getTempCByIndex(0); 

  // --- 5. LOGOWANIE DANYCH TELEMETRYCZNYCH DO PORTU SZEREGOWEGO ---
  Serial.println("\n================= ZBIÓR METRYK POMIAROWYCH =================");
  if (currentSpeed >= 0) {
    Serial.print("  Prędkość wiatru  : "); Serial.print(currentSpeed, 1); Serial.println(" m/s");
  } else {
    Serial.println("  Prędkość wiatru  : Błąd komunikacji RS485");
  }
  if (currentDirection >= 0) {
    Serial.print("  Kierunek wiatru  : "); Serial.print(currentDirection); Serial.print("° ");
    printDirectionText(currentDirection);
  } else {
    Serial.println("  Kierunek wiatru  : Błąd komunikacji RS485");
  }
  if (bmpStatus && !isnan(bmpTemp)) {
    Serial.print("  Ciśnienie ATM    : "); Serial.print(bmpPressure, 1); Serial.println(" hPa");
  }
  if (!isnan(dhtTemp) && !isnan(dhtHumidity)) {
    Serial.print("  Wilgotność wzgl. : "); Serial.print(dhtHumidity, 1); Serial.println(" %");
    Serial.print("  Temperatura pow. : "); Serial.print(dhtTemp, 1); Serial.println(" °C");
  }
  if (dsTemp != DEVICE_DISCONNECTED_C) {
    Serial.print("  Temperatura gleby: "); Serial.print(dsTemp, 1); Serial.println(" °C");
  }
  if (bh1750Status && lux >= 0) {
    Serial.print("  Jasność otoczenia: "); Serial.print(lux, 1); Serial.println(" lx");
  }
  Serial.println("============================================================");

  // --- 6. TRANSMISJA HTTP GET DO BAZY DANYCH (GŁÓWNY KANAŁ TRANSMISJI) ---
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    
    // Priorytet dla dokładniejszego czujnika temperatury powietrza
    float mainTemperature = (!isnan(dhtTemp)) ? dhtTemp : bmpTemp;

    // Budowanie bezpiecznego, parametryzowanego adresu URL żądania
    String fullUrl = String(serverPath) 
                   + "?api_key="        + String(apiKey)
                   + "&temperature="    + (isnan(mainTemperature) ? "0.0" : String(mainTemperature, 1)) 
                   + "&pressure="       + (isnan(bmpPressure) ? "0.0" : String(bmpPressure, 2)) 
                   + "&humidity="       + (isnan(dhtHumidity) ? "0.0" : String(dhtHumidity, 1)) 
                   + "&lux="            + (lux < 0 ? "0" : String(lux, 0)) 
                   + "&wind_speed="     + (currentSpeed < 0 ? "0.0" : String(currentSpeed, 1)) 
                   + "&wind_direction=" + (currentDirection < 0 ? "0" : String(currentDirection, 0));

    Serial.print("[HTTP] Wysyłanie pakietu danych: ");
    Serial.println(fullUrl);
    
    http.begin(fullUrl);
    int httpResponseCode = http.GET();
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.print("[HTTP] Kod odpowiedzi: "); Serial.println(httpResponseCode);
      Serial.print("[HTTP] Odpowiedź serwera: "); Serial.println(response);
    } else {
      Serial.print("[HTTP ERROR] Transmisja nieudana: ");
      Serial.println(http.errorToString(httpResponseCode).c_str());
    }
    http.end();
  }

  // Interwał wysyłania pomiarów (5 sekund)
  delay(5000); 
}

float readWindSpeed() {
  while(Serial2.available() > 0) Serial2.read();
  
  digitalWrite(RE_DE_WIND, HIGH); // Przełączenie max485 w tryb nadawania
  delay(2);
  
  Serial2.write(requestFrameWindSpeed, sizeof(requestFrameWindSpeed));
  Serial2.flush();
  delayMicroseconds(100);
  
  digitalWrite(RE_DE_WIND, LOW); // Powrót do nasłuchiwania linii magistrali
  
  unsigned long startWait = millis();
  while (Serial2.available() < 7 && (millis() - startWait < 150));

  if (Serial2.available() >= 7) {
    for (int i = 0; i < 7; i++) {
      responseFrameWindSpeed[i] = Serial2.read();
    }
    if (responseFrameWindSpeed[0] == 0x01 && responseFrameWindSpeed[1] == 0x03 && responseFrameWindSpeed[2] == 0x02) {
      unsigned int rawSpeed = (responseFrameWindSpeed[3] << 8) | responseFrameWindSpeed[4];
      return rawSpeed / 10.0;
    }
  }
  return -1.0; 
}

int readWindDirection() {
  while(Serial1.available() > 0) Serial1.read();
  
  digitalWrite(RE_DE_DIR, HIGH);
  delay(2);
  
  Serial1.write(requestFrameWindDirection, sizeof(requestFrameWindDirection));
  Serial1.flush();
  delayMicroseconds(100);
  
  digitalWrite(RE_DE_DIR, LOW);
  
  unsigned long startWait = millis();
  while (Serial1.available() < 7 && (millis() - startWait < 150));

  if (Serial1.available() >= 7) {
    for (int i = 0; i < 7; i++) {
      responseFrameWindDirection[i] = Serial1.read();
    }
    if (responseFrameWindDirection[0] == 0x01 && responseFrameWindDirection[1] == 0x03 && responseFrameWindDirection[2] == 0x02) {
      unsigned int rawDirection = (responseFrameWindDirection[3] << 8) | responseFrameWindDirection[4];
      return rawDirection; 
    }
  }
  return -1; 
}

void printDirectionText(int degrees) {
  if (degrees >= 338 || degrees < 23)       Serial.println("(N - Północ)");
  else if (degrees >= 23 && degrees < 68)   Serial.println("(NE - Północny-Wschód)");
  else if (degrees >= 68 && degrees < 113)  Serial.println("(E - Wschód)");
  else if (degrees >= 113 && degrees < 158) Serial.println("(SE - Południowy-Wschód)");
  else if (degrees >= 158 && degrees < 203) Serial.println("(S - Południe)");
  else if (degrees >= 203 && degrees < 248) Serial.println("(SW - Południowy-Zachód)");
  else if (degrees >= 248 && degrees < 293) Serial.println("(W - Zachód)");
  else if (degrees >= 293 && degrees < 338) Serial.println("(NW - Północny-Zachód)");
}