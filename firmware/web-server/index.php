<?php
/**
 * System Stacji Pogodowej - Panel Główny (Dashboard Premium)
 * 
 * Agreguje dane telemetryczne z bazy MySQL, renderuje interaktywne wykresy historyczne (Chart.js)
 * oraz obsługuje stabilne wyświetlanie prognozy i astronomii na bazie lokalnego pliku cache JSON.
 */

// Generowanie bezpiecznego tokenu Nonce dla poprawnego działania CSP (Content Security Policy)
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; object-src 'none';");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Panel Stacji Pogodowej Dark Premium</title>
	<!-- Ładowanie biblioteki Chart.js ze sprawdzaniem integralności plików (SRI) -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
	<style>
		:root {
			--bg-color: #0f172a;
			--card-bg: #1e293b;
			--primary-text: #f8fafc;
			--secondary-text: #94a3b8;
			--accent-color: #38bdf8;
			--border-color: #334155;
		}
		
		body {
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			background-color: var(--bg-color);
			color: var(--primary-text);
			margin: 0;
			padding: 20px;
		}

		.container {
			max-width: 1400px;
			margin: 0 auto;
		}

		header {
			margin-bottom: 25px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			gap: 15px;
			border-bottom: 1px solid var(--border-color);
			padding-bottom: 20px;
		}

		h1 {
			margin: 0;
			font-size: 1.8rem;
			color: var(--primary-text);
		}

		.last-update {
			font-size: 0.9rem;
			color: var(--secondary-text);
			background: var(--card-bg);
			padding: 8px 16px;
			border-radius: 8px;
			border: 1px solid var(--border-color);
		}

		.main-layout {
			display: grid;
			grid-template-columns: 1fr 300px;
			gap: 20px;
			margin-bottom: 35px;
		}

		@media (max-width: 1100px) {
			.main-layout {
				grid-template-columns: 1fr;
			}
		}

		.dashboard {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 20px;
		}

		.card {
			background: var(--card-bg);
			padding: 20px;
			border-radius: 16px;
			border: 1px solid var(--border-color);
			position: relative;
			overflow: hidden;
		}

		.card-title {
			font-size: 0.85rem;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			color: var(--secondary-text);
			margin-bottom: 10px;
			font-weight: 600;
		}

		.card-value {
			font-size: 2.2rem;
			font-weight: 700;
			color: var(--primary-text);
		}

		.card-unit {
			font-size: 1.1rem;
			font-weight: 400;
			color: var(--secondary-text);
			margin-left: 2px;
		}

		.sidebar-widgets {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.widget-card {
			background: var(--card-bg);
			padding: 20px;
			border-radius: 16px;
			border: 1px solid var(--border-color);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
		}

		.thermo-tube {
			width: 20px;
			height: 150px;
			background: #334155;
			border-radius: 12px 12px 0 0;
			position: relative;
			border: 2px solid #475569;
			border-bottom: none;
			margin-top: 10px;
		}

		.thermo-fluid {
			width: 100%;
			height: 0%;
			background: linear-gradient(to top, #ef4444, #f97316);
			position: absolute;
			bottom: 0;
			border-radius: 8px 8px 0 0;
			transition: height 1s ease-in-out;
		}

		.thermo-bulb {
			width: 38px;
			height: 38px;
			background: #ef4444;
			border-radius: 50%;
			border: 2px solid #475569;
			margin-top: -5px;
			box-shadow: inset 0 -4px 8px rgba(0,0,0,0.4);
			margin-bottom: 10px;
		}

		.compass-dial {
			width: 130px;
			height: 130px;
			border: 3px solid #475569;
			border-radius: 50%;
			position: relative;
			background: #0f172a;
			margin: 15px 0 10px 0;
			box-shadow: inset 0 0 10px rgba(0,0,0,0.6);
		}

		.compass-label {
			position: absolute;
			font-weight: 700;
			font-size: 0.8rem;
			color: var(--secondary-text);
		}
		.compass-n { top: 4px; left: 50%; transform: translateX(-50%); color: #ef4444; }
		.compass-s { bottom: 4px; left: 50%; transform: translateX(-50%); }
		.compass-e { right: 8px; top: 50%; transform: translateY(-50%); }
		.compass-w { left: 8px; top: 50%; transform: translateY(-50%); }

		.compass-needle {
			width: 6px;
			height: 110px;
			position: absolute;
			top: 10px;
			left: calc(50% - 3px);
			transform-origin: 50% 50%;
			transition: transform 1.2s cubic-bezier(0.25, 1, 0.5, 1);
		}

		.needle-north {
			width: 0;
			height: 0;
			border-left: 3px solid transparent;
			border-right: 3px solid transparent;
			border-bottom: 55px solid #ef4444;
		}

		.needle-south {
			width: 0;
			height: 0;
			border-left: 3px solid transparent;
			border-right: 3px solid transparent;
			border-top: 55px solid #94a3b8;
		}

		.compass-center {
			width: 10px;
			height: 10px;
			background: #f8fafc;
			border-radius: 50%;
			position: absolute;
			top: calc(50% - 5px);
			left: calc(50% - 5px);
			border: 2px solid #0f172a;
			z-index: 10;
		}

		.section-box {
			background: var(--card-bg);
			padding: 25px;
			border-radius: 16px;
			border: 1px solid var(--border-color);
			margin-bottom: 35px;
		}

		h2 {
			font-size: 1.3rem;
			margin-top: 0;
			margin-bottom: 20px;
			color: var(--primary-text);
		}

		.filter-form {
			display: flex;
			gap: 15px;
			align-items: flex-end;
			flex-wrap: wrap;
			margin-bottom: 25px;
			background: #1e293b;
			padding: 15px;
			border-radius: 12px;
			border: 1px solid var(--border-color);
		}

		.filter-group {
			display: flex;
			flex-direction: column;
			gap: 5px;
		}

		.filter-group label {
			font-size: 0.8rem;
			font-weight: 600;
			color: var(--secondary-text);
		}

		.filter-group input {
			padding: 8px 12px;
			border: 1px solid var(--border-color);
			background-color: #0f172a;
			color: white;
			border-radius: 6px;
			font-family: inherit;
		}

		.btn-filter {
			background: var(--accent-color);
			color: #0f172a;
			border: none;
			padding: 9px 20px;
			border-radius: 6px;
			cursor: pointer;
			font-weight: 600;
			transition: opacity 0.2s;
		}

		.btn-filter:hover {
			opacity: 0.9;
		}

		.chart-switcher {
			display: flex;
			gap: 10px;
			margin-bottom: 20px;
			flex-wrap: wrap;
		}

		.switch-btn {
			background: #334155;
			color: var(--secondary-text);
			border: 1px solid var(--border-color);
			padding: 8px 16px;
			border-radius: 8px;
			cursor: pointer;
			font-weight: 600;
			font-size: 0.85rem;
			transition: all 0.2s;
		}

		.switch-btn:hover {
			background: #475569;
			color: white;
		}

		.switch-btn.active {
			background: var(--accent-color);
			color: #0f172a;
			border-color: var(--accent-color);
		}

		.chart-container {
			position: relative;
			height: 400px;
			width: 100%;
		}

		.table-responsive {
			overflow-x: auto;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		th, td {
			padding: 12px 15px;
			text-align: left;
			border-bottom: 1px solid var(--border-color);
			font-size: 0.9rem;
		}

		th {
			background-color: #0f172a;
			color: var(--secondary-text);
			font-weight: 600;
			text-transform: uppercase;
			font-size: 0.75rem;
		}

		tr:hover {
			background-color: #243249;
		}

		.forecast-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 15px;
			margin-bottom: 20px;
		}
		.forecast-card {
			background: #131c2e;
			border: 1px solid var(--border-color);
			border-radius: 12px;
			padding: 15px;
			text-align: center;
		}
		.forecast-date {
			font-size: 0.9rem;
			font-weight: 600;
			color: var(--accent-color);
			margin-bottom: 8px;
		}
		.forecast-temp {
			font-size: 1.4rem;
			font-weight: 700;
			margin: 5px 0;
		}
		.forecast-extra {
			font-size: 0.8rem;
			color: var(--secondary-text);
			margin-top: 5px;
		}
		.astro-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 20px;
			margin-top: 20px;
			border-top: 1px dashed var(--border-color);
			padding-top: 20px;
		}
		.astro-item {
			background: #1e293b;
			padding: 15px;
			border-radius: 12px;
			border-left: 4px solid var(--accent-color);
		}
	</style>
</head>
<body>

	<div class="container">
		<?php
			// Konfiguracja Bazy Danych (Zanonimizowana do portfolio)
			$host     = 'localhost';
			$user     = 'NAZWA_UZYTKOWNIKA_BAZY';
			$password = 'HASLO_DO_BAZY_DANYCH';
			$dbname   = 'NAZWA_BAZY_STACJI_METEO';
			
			$conn = mysqli_connect($host, $user, $password, $dbname);
			
			if(!$conn) {
				echo "<div style='background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px;'>Błąd połączenia z lokalną bazą danych stacji!</div>";
				die();
			}

			function validateDate($date, $default) {
				if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
					return $date;
				}
				return $default;
			}

			$date_from_raw = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-3 days'));
			$date_to_raw   = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

			$date_from = validateDate($date_from_raw, date('Y-m-d', strtotime('-3 days')));
			$date_to   = validateDate($date_to_raw, date('Y-m-d'));

			$sql_latest = "SELECT * FROM `pomiary` ORDER BY `data_pomiaru` DESC LIMIT 1;";
			$res_latest = mysqli_query($conn, $sql_latest);
			$latest     = mysqli_fetch_array($res_latest);

			$latest_date_fixed = 'Brak danych';
			$current_temp      = 0;
			$current_wind_dir  = null;

			if ($latest) {
				$latest_date_fixed = date('Y-m-d H:i:s', strtotime($latest["data_pomiaru"] . ' +1 hour'));
				$current_temp      = $latest["temperatura"];
				$current_wind_dir  = $latest["wiatr_kierunek"];
			}

			function formatWindDir($degrees) {
				if ($degrees === null || $degrees === '') return '-';
				if ($degrees >= 338 || $degrees < 23)   return "N";
				if ($degrees >= 23 && $degrees < 68)     return "NE";
				if ($degrees >= 68 && $degrees < 113)    return "E";
				if ($degrees >= 113 && $degrees < 158)   return "SE";
				if ($degrees >= 158 && $degrees < 203)   return "S";
				if ($degrees >= 203 && $degrees < 248)   return "SW";
				if ($degrees >= 248 && $degrees < 293)   return "W";
				if ($degrees >= 293 && $degrees < 338)   return "NW";
				return $degrees . "°";
			}
		?>

		<header>
			<div>
				<h1>Stacja Meteo</h1>
				<div style="font-size:0.85rem; color:var(--secondary-text);">Projekt sowatech_krzysiek</div>
			</div>
			<div class="last-update">
				Aktualizacja danych: <b><?php echo htmlspecialchars($latest_date_fixed); ?></b>
			</div>
		</header>

		<div class="main-layout">
			<div class="dashboard">
				<div class="card" style="border-top-color: #ef4444;">
					<div class="card-title">Temperatura</div>
					<div class="card-value">
						<?php echo $latest && $latest["temperatura"] !== null ? number_format($latest["temperatura"], 1) : '-'; ?><span class="card-unit">°C</span>
					</div>
				</div>

				<div class="card" style="border-top-color: #38bdf8;">
					<div class="card-title">Ciśnienie</div>
					<div class="card-value">
						<?php echo $latest && $latest["cisnienie"] !== null ? number_format($latest["cisnienie"], 0) : '-'; ?><span class="card-unit">hPa</span>
					</div>
				</div>

				<div class="card" style="border-top-color: #10b981;">
					<div class="card-title">Wilgotność</div>
					<div class="card-value">
						<?php echo $latest && $latest["wilgotnosc"] !== null ? number_format($latest["wilgotnosc"], 0) : '-'; ?><span class="card-unit">%</span>
					</div>
				</div>

				<div class="card" style="border-top-color: #fbbf24;">
					<div class="card-title">Światło</div>
					<div class="card-value">
						<?php echo $latest && $latest["naswietlenie"] !== null ? number_format($latest["naswietlenie"], 0) : '-'; ?><span class="card-unit">lx</span>
					</div>
				</div>

				<div class="card" style="border-top-color: #a855f7;">
					<div class="card-title">Prędkość wiatru</div>
					<div class="card-value">
						<?php echo $latest && $latest["wiatr_predkosc"] !== null ? number_format($latest["wiatr_predkosc"], 1) : '-'; ?><span class="card-unit">m/s</span>
					</div>
				</div>

				<div class="card" style="border-top-color: #ec4899;">
					<div class="card-title">Kierunek wiatru</div>
					<div class="card-value">
						<?php echo $latest ? htmlspecialchars(formatWindDir($latest["wiatr_kierunek"])) : '-'; ?>
						<?php if($latest && $latest["wiatr_kierunek"] !== null) { echo '<span class="card-unit">(' . intval($latest["wiatr_kierunek"]) . '°)</span>'; } ?>
					</div>
				</div>
			</div>

			<div class="sidebar-widgets">
				<div class="widget-card">
					<div class="card-title">Termometr</div>
					<div class="thermo-tube">
						<div class="thermo-fluid" id="thermoFluid"></div>
					</div>
					<div class="thermo-bulb"></div>
					<div style="font-weight: bold; color: #ef4444; font-size:1.1rem;">
						<?php echo number_format($current_temp, 1); ?> °C
					</div>
				</div>

				<div class="widget-card">
					<div class="card-title">Kompas Wiatru</div>
					<div class="compass-dial">
						<div class="compass-label compass-n">N</div>
						<div class="compass-label compass-e">E</div>
						<div class="compass-label compass-s">S</div>
						<div class="compass-label compass-w">W</div>
						
						<div class="compass-needle" id="compassNeedle">
							<div class="needle-north"></div>
							<div class="needle-south"></div>
						</div>
						<div class="compass-center"></div>
					</div>
					<div style="font-weight: bold; color: #ec4899; font-size:1.1rem;">
						<?php 
							if($latest && $current_wind_dir !== null) {
								echo htmlspecialchars(formatWindDir($current_wind_dir)) . ' (' . intval($current_wind_dir) . '°)'; 
							} else {
								echo 'Brak danych';
							}
						?>
					</div>
				</div>
			</div>
		</div>

		<!-- ================= SEKCJA: PROGNOZA I ASTRONOMIA Z LOKALNEGO CACHE JSON ================= -->
		<div class="section-box">
			<h2>Prognoza Pogody i Astronomia (Kędzierzyn-Koźle)</h2>
			<?php
				$plik_prognozy = 'prognoza.json';
				
				// MATEMATYCZNY ALGORYTM WYLICZANIA FAZY KSIĘŻYCA (Stabilny, unika zapytań API)
				function obliczFazeKsiezyca($rok, $miesiac, $dzien) {
					if ($miesiac < 3) { $rok--; $miesiac += 12; }
					$miesiac++;
					$c = 365.25 * $rok;
					$e = 30.6 * $miesiac;
					$jd = $c + $e + $dzien - 694039.09;
					$jd /= 29.530588853; 
					$faza = $jd - floor($jd); 
					
					if ($faza < 0.03 || $faza > 0.97) return 'Nów 🌑';
					if ($faza >= 0.03 && $faza < 0.22) return 'Przyrastający Sierp 🌒';
					if ($faza >= 0.22 && $faza < 0.28) return 'Pierwsza Kwadra 🌓';
					if ($faza >= 0.28 && $faza < 0.47) return 'Przyrastający Garbaty 🌔';
					if ($faza >= 0.47 && $faza < 0.53) return 'Pełnia 🌕';
					if ($faza >= 0.53 && $faza < 0.72) return 'Ubywający Garbaty 🌖';
					if ($faza >= 0.72 && $faza < 0.78) return 'Ostatnia Kwadra 🌗';
					return 'Ubywający Sierp 🌘';
				}

				if (file_exists($plik_prognozy)) {
					$apiResponse = file_get_contents($plik_prognozy);
					$forecastData = json_decode($apiResponse, true);
					
					if (isset($forecastData['daily'])) {
						$daily = $forecastData['daily'];
						
						echo '<div class="forecast-grid">';
						for ($i = 0; $i < 5; $i++) {
							if (!isset($daily['time'][$i])) break;
							$dateFormatted = date('d.m (D)', strtotime($daily['time'][$i]));
							$maxT = number_format($daily['temperature_2m_max'][$i], 1);
							$minT = number_format($daily['temperature_2m_min'][$i], 1);
							
							echo '<div class="forecast-card">';
							echo '<div class="forecast-date">' . $dateFormatted . '</div>';
							echo '<div class="forecast-temp" style="color:#ef4444;">' . $maxT . '°C</div>';
							echo '<div class="forecast-temp" style="color:#38bdf8; font-size:1.1rem;">' . $minT . '°C</div>';
							echo '</div>';
						}
						echo '</div>';
						
						$sunrise = date('H:i', strtotime($daily['sunrise'][0]));
						$sunset = date('H:i', strtotime($daily['sunset'][0]));
						$dzis_faza = obliczFazeKsiezyca(intval(date('Y')), intval(date('m')), intval(date('d')));
						
						echo '<div class="astro-grid">';
						echo '<div class="astro-item"><b>Słońce ☀️</b><div class="forecast-extra">Wschód: ' . $sunrise . '<br>Zachód: ' . $sunset . '</div></div>';
						echo '<div class="astro-item"><b>Faza Księżyca 🔮</b><div class="forecast-extra" style="font-size:1rem; color:#f8fafc; margin-top:5px;">' . $dzis_faza . '</div></div>';
						echo '</div>';
					} else {
						echo '<div style="color:var(--secondary-text); font-size:0.9rem;">Błąd: Niepoprawny format struktury danych pliku cache prognozy.</div>';
					}
				} else {
					echo '<div style="color:var(--secondary-text); font-size:0.9rem;">Oczekiwanie na pierwsze uruchomienie synchronizacji danych w celu wygenerowania prognozy...</div>';
				}
			?>
		</div>

		<div class="section-box">
			<h2>Analiza Trendów Pogodowych</h2>
			
			<form class="filter-form" method="GET" action="">
				<div class="filter-group">
					<label for="date_from">Data od:</label>
					<input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
				</div>
				<div class="filter-group">
					<label for="date_to">Data do:</label>
					<input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
				</div>
				<button type="submit" class="btn-filter">Filtruj dane</button>
			</form>

			<div class="chart-switcher">
				<button class="switch-btn active" id="btn-temp">Temperatura</button>
				<button class="switch-btn" id="btn-press">Ciśnienie</button>
				<button class="switch-btn" id="btn-hum">Wilgotność</button>
				<button class="switch-btn" id="btn-lux">Oświetlenie</button>
				<button class="switch-btn" id="btn-wind">Prędkość wiatru</button>
			</div>

			<?php
				$sql_chart = "SELECT DATE_ADD(`data_pomiaru`, INTERVAL 1 HOUR) as data_korekta, temperatura, cisnienie, wilgotnosc, naswietlenie, wiatr_predkosc 
							  FROM `pomiary` 
							  WHERE DATE(`data_pomiaru`) BETWEEN ? AND ? 
							  ORDER BY `data_pomiaru` ASC;";
				
				$chart_labels = [];
				$chart_temp   = [];
				$chart_press  = [];
				$chart_hum    = [];
				$chart_lux    = [];
				$chart_wind   = [];

				if ($stmt = mysqli_prepare($conn, $sql_chart)) {
					mysqli_stmt_bind_param($stmt, "ss", $date_from, $date_to);
					mysqli_stmt_execute($stmt);
					$res_chart = mysqli_stmt_get_result($stmt);

					while($c_row = mysqli_fetch_array($res_chart)) {
						$chart_labels[] = date('d.m H:i', strtotime($c_row['data_korekta']));
						$chart_temp[]   = $c_row['temperatura'] !== null ? floatval($c_row['temperatura']) : null;
						$chart_press[]  = $c_row['cisnienie'] !== null ? floatval($c_row['cisnienie']) : null;
						$chart_hum[]    = $c_row['wilgotnosc'] !== null ? floatval($c_row['wilgotnosc']) : null;
						$chart_lux[]    = $c_row['naswietlenie'] !== null ? floatval($c_row['naswietlenie']) : null;
						$chart_wind[]   = $c_row['wiatr_predkosc'] !== null ? floatval($c_row['wiatr_predkosc']) : null;
					}
					mysqli_stmt_close($stmt);
				}
			?>

			<div class="chart-container">
				<canvas id="weatherChart"></canvas>
			</div>
		</div>

		<div class="section-box">
			<h2>Ostatnie pomiary (Historia)</h2>
			<div class="table-responsive">
				<table>
					<thead>
						<tr>
							<th>Data</th>
							<th>Temp.</th>
							<th>Ciśnienie</th>
							<th>Wilgotność</th>
							<th>Jasność</th>
							<th>Wiatr</th>
						</tr>
					</thead>
					<tbody>
						<?php
							$sql_history = "SELECT * FROM `pomiary` ORDER BY `data_pomiaru` DESC LIMIT 10 OFFSET 1;";
							$res_history = mysqli_query($conn, $sql_history);

							if($res_history && mysqli_num_rows($res_history) > 0) {
								while($row = mysqli_fetch_array($res_history)) {
									$row_date_fixed = date('Y-m-d H:i:s', strtotime($row["data_pomiaru"] . ' +1 hour'));

									echo '<tr>';
									echo '<td>' . htmlspecialchars($row_date_fixed) . '</td>';
									echo '<td>' . ($row["temperatura"] !== null ? number_format($row["temperatura"], 1) . ' °C' : '-') . '</td>';
									echo '<td>' . ($row["cisnienie"] !== null ? number_format($row["cisnienie"], 0) . ' hPa' : '-') . '</td>';
									echo '<td>' . ($row["wilgotnosc"] !== null ? number_format($row["wilgotnosc"], 0) . ' %' : '-') . '</td>';
									echo '<td>' . ($row["naswietlenie"] !== null ? number_format($row["naswietlenie"], 0) . ' lx' : '-') . '</td>';
									echo '<td>' . ($row["wiatr_predkosc"] !== null ? number_format($row["wiatr_predkosc"], 1) . ' m/s ' . htmlspecialchars(formatWindDir($row["wiatr_kierunek"])) : '-') . '</td>';
									echo '</tr>';
								}
							} else {
								echo '<tr><td colspan="6" style="text-align:center;">Brak dostępnych danych we wskazanym okresie</td></tr>';
							}
							
							mysqli_close($conn);
						?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Bezpieczne skrypty inline autoryzowane unikalnym tokenem nonce -->
	<script nonce="<?php echo $nonce; ?>">
		const currentTemp = <?php echo floatval($current_temp); ?>;
		const minTemp = -20;
		const maxTemp = 40;
		let percentage = ((currentTemp - minTemp) / (maxTemp - minTemp)) * 100;
		if (percentage > 100) percentage = 100;
		if (percentage < 0) percentage = 0;
		
		setTimeout(() => {
			const tf = document.getElementById('thermoFluid');
			if(tf) tf.style.height = percentage + '%';
		}, 300);

		const windDir = <?php echo $current_wind_dir !== null ? intval($current_wind_dir) : 'null'; ?>;
		if (windDir !== null) {
			setTimeout(() => {
				const cn = document.getElementById('compassNeedle');
				if(cn) cn.style.transform = `rotate(${windDir}deg)`;
			}, 500);
		} else {
			const cn = document.getElementById('compassNeedle');
			if(cn) cn.style.display = 'none';
		}

		const labels = <?php echo json_encode($chart_labels); ?>;
		
		const dataConfigs = {
			temp: {
				label: 'Temperatura (°C)',
				data: <?php echo json_encode($chart_temp); ?>,
				borderColor: '#ef4444',
				backgroundColor: 'rgba(239, 68, 68, 0.15)',
			},
			press: {
				label: 'Ciśnienie (hPa)',
				data: <?php echo json_encode($chart_press); ?>,
				borderColor: '#38bdf8',
				backgroundColor: 'rgba(56, 189, 248, 0.15)',
			},
			hum: {
				label: 'Wilgotność (%)',
				data: <?php echo json_encode($chart_hum); ?>,
				borderColor: '#10b981',
				backgroundColor: 'rgba(16, 185, 129, 0.15)',
			},
			lux: {
				label: 'Oświetlenie (lx)',
				data: <?php echo json_encode($chart_lux); ?>,
				borderColor: '#fbbf24',
				backgroundColor: 'rgba(251, 191, 36, 0.15)',
			},
			wind: {
				label: 'Prędkość wiatru (m/s)',
				data: <?php echo json_encode($chart_wind); ?>,
				borderColor: '#a855f7',
				backgroundColor: 'rgba(168, 85, 247, 0.15)',
			}
		};

		const ctx = document.getElementById('weatherChart').getContext('2d');
		
		const weatherChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: dataConfigs.temp.label,
					data: dataConfigs.temp.data,
					borderColor: dataConfigs.temp.borderColor,
					backgroundColor: dataConfigs.temp.backgroundColor,
					fill: true,
					tension: 0.3,
					borderWidth: 3,
					pointRadius: 2,
					pointHoverRadius: 6
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { labels: { color: '#f8fafc' } }
				},
				scales: {
					x: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } },
					y: { grid: { color: '#334155' }, ticks: { color: '#94a3b8' } }
				}
			}
		});

		function changeChartDataset(type, buttonId) {
			document.querySelectorAll('.switch-btn').forEach(btn => btn.classList.remove('active'));
			document.getElementById(buttonId).classList.add('active');

			weatherChart.data.datasets[0].label = dataConfigs[type].label;
			weatherChart.data.datasets[0].data = dataConfigs[type].data;
			weatherChart.data.datasets[0].borderColor = dataConfigs[type].borderColor;
			weatherChart.data.datasets[0].backgroundColor = dataConfigs[type].backgroundColor;
			
			weatherChart.update();
		}

		document.getElementById('btn-temp').addEventListener('click', function() { changeChartDataset('temp', 'btn-temp'); });
		document.getElementById('btn-press').addEventListener('click', function() { changeChartDataset('press', 'btn-press'); });
		document.getElementById('btn-hum').addEventListener('click', function() { changeChartDataset('hum', 'btn-hum'); });
		document.getElementById('btn-lux').addEventListener('click', function() { changeChartDataset('lux', 'btn-lux'); });
		document.getElementById('btn-wind').addEventListener('click', function() { changeChartDataset('wind', 'btn-wind'); });
	</script>
</body>
</html>