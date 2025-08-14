<?php
$input = "points.csv";
$rejectsLog = "rejects.log";
$minGapMinutes = 25;
$minDistanceKm = 2;

function debug($data) {
	if(is_array($data) || is_object($data)) {
		print "<pre>";
		print_r($data);
		print "</pre>";
	}
	else
		print $data;
} 

function isValidCoord($lat, $lon) {
    return is_numeric($lat) && is_numeric($lon)
        && $lat >= -90 && $lat <= 90
        && $lon >= -180 && $lon <= 180;
}

function isValidTimestamp($ts) {
    return strtotime($ts) !== false;
}

function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}
function randomColor() {
    return sprintf("#%06X", mt_rand(0, 0xFFFFFF));
}

$rows = [];
$rejects = [];

if (($handle = fopen($input, "r")) !== false) {
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 4 || $data[0] === "device_id") continue;
        list($id, $lat, $lon, $ts) = $data;

        if (!isValidCoord($lat, $lon) || !isValidTimestamp($ts)) {
            $rejects[] = implode(",", $data);
            continue;
        }

        $rows[] = [
            'device_id' => $id,
            'lat' => (float)$lat,
            'lon' => (float)$lon,
            'timestamp' => $ts,
            'time_unix' => strtotime($ts)
        ];
    }
    fclose($handle);
}

if (!empty($rejects)) {
    file_put_contents($rejectsLog, implode(PHP_EOL, $rejects) . PHP_EOL);
}

usort($rows, fn($a, $b) => $a['time_unix'] <=> $b['time_unix']);


//debug($rows);

$trips = [];
$currentTrip = [];
$tripNum = 1;

foreach ($rows as $i => $row) {
    if (empty($currentTrip)) {
        $currentTrip[] = $row;
        continue;
    }

    $prev = end($currentTrip);
    $timeGap = ($row['time_unix'] - $prev['time_unix']) / 60; // minutes
    $distGap = haversine($prev['lat'], $prev['lon'], $row['lat'], $row['lon']);

    if ($timeGap > $minGapMinutes || $distGap > $minDistanceKm) {
        $trips["trip_$tripNum"] = $currentTrip;
        $tripNum++;
        $currentTrip = [$row];
    } else {
        $currentTrip[] = $row;
    }
}

if (!empty($currentTrip)) {
    $trips["trip_$tripNum"] = $currentTrip;
}

//debug($trips);

$tripStats = [];

foreach ($trips as $tripName => $points) {
    $totalDistance = 0;
    $maxSpeed = 0;

    for ($i = 1; $i < count($points); $i++) {
        $dist = haversine(
            $points[$i-1]['lat'], $points[$i-1]['lon'],
            $points[$i]['lat'], $points[$i]['lon']
        );
        $timeHrs = ($points[$i]['time_unix'] - $points[$i-1]['time_unix']) / 3600;

        if ($timeHrs > 0) {
            $speed = $dist / $timeHrs;
            if ($speed > $maxSpeed) $maxSpeed = $speed;
        }

        $totalDistance += $dist;
    }

    $durationMin = (end($points)['time_unix'] - $points[0]['time_unix']) / 60;
    $avgSpeed = $durationMin > 0 ? $totalDistance / ($durationMin / 60) : 0;

    $tripStats[$tripName] = [
        'total_distance_km' => round($totalDistance, 3),
        'duration_min' => round($durationMin, 1),
        'avg_speed_kmh' => round($avgSpeed, 2),
        'max_speed_kmh' => round($maxSpeed, 2),
    ];
}

//debug($tripStats);

$features = [];

foreach ($trips as $tripName => $points) {
    $coords = [];
    foreach ($points as $p) {
        $coords[] = [$p['lon'], $p['lat']];
    }

    $features[] = [
        'type' => 'Feature',
        'properties' => array_merge([
            'trip_name' => $tripName,
            'stroke' => randomColor(),      
            'stroke-width' => 4,
            'stroke-opacity' => 0.8
        ], $tripStats[$tripName]),
        'geometry' => [
            'type' => 'LineString',
            'coordinates' => $coords
        ]
    ];
}

$geojson = [
    'type' => 'FeatureCollection',
    'features' => $features
];

header('Content-Type: application/json');
echo json_encode($geojson, JSON_PRETTY_PRINT);

?>