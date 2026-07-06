# 🌤️ Zintegrowany System Stacji Pogodowej IoT z Modułem Awaryjnym (Failover)

Kompleksowy, produkcyjny system telemetryczny stacji pogodowej realizujący pełną ścieżkę danych: od fizycznego pomiaru za pomocą sensorów, przez bezprzewodową transmisję, aż po bezpieczną agregację w bazie danych oraz interaktywną wizualizację w panelu użytkownika.

Projekt wyróżnia się implementacją **dwóch niezależnych źródeł danych** z automatyczną architekturą zapasową (**Failover / Fallback Solution**), co gwarantuje 100% ciągłości wykresów historycznych nawet w przypadku awarii fizycznego sprzętu.

---

## 🏗️ Architektura i Przepływ Danych (Data Flow)

System został zaprojektowany w architekturze modułowej i dzieli się na trzy kluczowe warstwy:

```text
+-----------------------------------+
|    A. STACJA SPRZĘTOWA IOT        | --> [ Pomiary Fizyczne ]
|    Mikrokontroler ESP32           |
+-----------------------------------+
                  | (Główny Kanał HTTP GET)
                  v
+-----------------------------------+
|    B. BACKEND SERWEROWY           | --> [ Walidacja i Zapis ] --> [ Baza MySQL ]
|    Skrypty PHP + Apache (.htaccess) |
+-----------------------------------+
                  ^
                  | (Kanał Awaryjny HTTP GET)
+-----------------------------------+
|    C. MODUŁ ZAPASOWY (FAILOVER)   | --> [ Open-Meteo API ]
|    Skrypt Node.js + Windows BAT   |
+-----------------------------------+
