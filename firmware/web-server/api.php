<?php
// 1. Zezwalamy każdemu uczniowi na pobieranie danych (tzw. CORS).
// Dzięki temu będą mogli testować kod na swoich komputerach (localhost).
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 2. Dane połączenia z Twoją bazą danych.
// UWAGA: Na GitHubie zostaw te przykładowe dane poniżej!
// Prawdziwe dane wpisz w tym pliku bezpośrednio na swoim serwerze produkcyjnym.
$host = 'localhost';
$user = 'TUTAJ_WPISZ_UZYTKOWNIKA';
$password = 'TUTAJ_WPISZ_HASLO';
$dbname = 'sowatech_stacjaPogodowa';

// Zabezpieczenie przed uruchomieniem na przykładowych danych
if ($user === 'TUTAJ_WPISZ_UZYTKOWNIKA') {
    echo json_encode(["error" => "Konfiguracja API: Uzupełnij dane dostępowe do bazy bezpośrednio w pliku api.php na serwerze."]);
    exit;
}

$conn = @mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    echo json_encode(["error" => "Brak połączenia z bazą stacji"]);
    exit;
}

// 3. Sprawdzamy, ile rekordów chce pobrać uczeń (domyślnie 1 - najnowszy, max 100 żeby nie przeciążyć serwera)
$limit = 1;
if (isset($_GET['limit'])) {
    $limit = intval($_GET['limit']);
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100; 
}

// 4. Pobieramy dane z bazy stacji
$sql = "SELECT 
            DATE_ADD(data_pomiaru, INTERVAL 1 HOUR) as czas, 
            temperatura, 
            wilgotnosc, 
            cisnienie, 
            naswietlenie, 
            wiatr_predkosc 
        FROM pomiary 
        ORDER BY id DESC 
        LIMIT $limit";

$result = mysqli_query($conn, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Konwertujemy liczby z bazy na właściwe typy (float), żeby uczeń miał czyste dane
    $data[] = [
        "czas" => date('Y-m-d H:i:s', strtotime($row['czas'])),
        "temperatura" => $row['temperatura'] !== null ? floatval($row['temperatura']) : null,
        "wilgotnosc" => $row['wilgotnosc'] !== null ? floatval($row['wilgotnosc']) : null,
        "cisnienie" => $row['cisnienie'] !== null ? floatval($row['cisnienie']) : null,
        "naswietlenie" => $row['naswietlenie'] !== null ? floatval($row['naswietlenie']) : null,
        "wiatr" => $row['wiatr_predkosc'] !== null ? floatval($row['wiatr_predkosc']) : null
    ];
}

mysqli_close($conn);

// 5. Jeśli uczeń chciał tylko 1 najnowszy pomiar, zwracamy pojedynczy obiekt (łatwiejszy w obsłudze)
if ($limit === 1 && !empty($data)) {
    echo json_encode($data[0], JSON_PRETTY_PRINT);
} else {
    echo json_encode($data, JSON_PRETTY_PRINT);
}
?>
