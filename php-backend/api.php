<?php
// api.php - PHP Backend for YouTube Platform
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
define('BIN_DIR', __DIR__ . '/bin'); // Place linux binaries of yt-dlp and ffmpeg here
define('CACHE_DIR', __DIR__ . '/cache');
define('YT_DLP', BIN_DIR . '/yt-dlp');
define('FFMPEG', BIN_DIR . '/ffmpeg');

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Set global PATH so both proc_open and passthru inherit it
$pythonBin = '/home/mertsame/virtualenv/youtube/3.11/bin';
$newPath = BIN_DIR . ':' . $pythonBin . ':' . getenv('PATH');
putenv("PATH=" . $newPath);
$_ENV['PATH'] = $newPath;

// Robust Routing for Shared Hosting
$route = $_SERVER['PATH_INFO'] ?? '';
if (empty($route)) {
    // Fallback: extract path from REQUEST_URI relative to script name
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($requestUri, $scriptName) === 0) {
        $route = substr($requestUri, strlen($scriptName));
    } else {
        $route = '/' . ltrim(str_replace(dirname($scriptName), '', $requestUri), '/');
    }
}
$route = '/' . ltrim($route, '/');

// Helper: Run command safely
function runCommand($cmd, &$stdout, &$stderr) {
    $pythonBin = '/home/mertsame/virtualenv/youtube/3.11/bin';
    $newPath = BIN_DIR . ':' . $pythonBin . ':' . getenv('PATH');
    $env = array_merge($_ENV, ['PATH' => $newPath]);
    
    $descriptorspec = [
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];
    
    $process = proc_open($cmd, $descriptorspec, $pipes, null, $env);
    
    if (is_resource($process)) {
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        return proc_close($process);
    }
    return -1;
}

// Helper: Input Validation
function isValidVideoId($id) {
    return preg_match('/^[a-zA-Z0-9_-]{11}$/', $id);
}

function isValidSearchQuery($q) {
    return preg_match('/^[\p{L}\p{N}\s\-_.!?,#+&%@()]+$/u', $q) && strlen($q) <= 100;
}

// Simple Cache helpers
function cacheGet($key, $ttl) {
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function cacheSet($key, $data) {
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    file_put_contents($file, json_encode($data));
}

// Simple DB Mock using JSON file
function getMockDb() {
    $file = CACHE_DIR . '/db.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return ['history' => [], 'favorites' => []];
}

function saveMockDb($db) {
    $file = CACHE_DIR . '/db.json';
    file_put_contents($file, json_encode($db));
}

// Route handler
switch ($route) {
    case '/search':
        $q = $_GET['q'] ?? '';
        if (!isValidSearchQuery($q)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid search query']);
            exit;
        }

        $cached = cacheGet('search_' . $q, 1800); // 30 mins
        if ($cached) {
            echo json_encode($cached);
            exit;
        }

        $escapedQ = escapeshellarg($q);
        $cmd = YT_DLP . " --no-update --flat-playlist --no-playlist --dump-json " . escapeshellarg("ytsearch20:" . $q);
        
        $stdout = '';
        $stderr = '';
        $code = runCommand($cmd, $stdout, $stderr);
        
        if ($code !== 0) {
            http_response_code(500);
            echo json_encode(['error' => 'Search failed: ' . $stderr]);
            exit;
        }

        $lines = explode("\n", trim($stdout));
        $results = [];
        foreach ($lines as $line) {
            $item = json_decode($line, true);
            if ($item && isset($item['id'])) {
                $results[] = [
                    'id' => $item['id'],
                    'title' => $item['title'] ?? '',
                    'channel' => $item['uploader'] ?? ($item['channel'] ?? ''),
                    'duration' => $item['duration_string'] ?? ($item['duration'] ?? ''),
                    'views' => $item['view_count'] ?? 0,
                    'date' => $item['upload_date'] ?? '',
                    'thumbnail' => isset($item['thumbnails']) ? end($item['thumbnails'])['url'] : null
                ];
            }
        }

        cacheSet('search_' . $q, $results);

        // Update history
        $db = getMockDb();
        $exists = false;
        foreach ($db['history'] as $h) {
            if ($h['query'] === $q) { $exists = true; break; }
        }
        if (!$exists) {
            array_unshift($db['history'], ['query' => $q, 'timestamp' => time()]);
            if (count($db['history']) > 50) array_pop($db['history']);
            saveMockDb($db);
        }

        echo json_encode($results);
        break;

    case '/video':
        $id = $_GET['id'] ?? '';
        if (!isValidVideoId($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid video ID']);
            exit;
        }

        $cached = cacheGet('video_' . $id, 86400); // 24 hours
        if ($cached) {
            echo json_encode($cached);
            exit;
        }

        $cmd = YT_DLP . " --no-update --dump-json " . escapeshellarg("https://www.youtube.com/watch?v=" . $id);
        $stdout = '';
        $stderr = '';
        $code = runCommand($cmd, $stdout, $stderr);

        if ($code !== 0) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch video info: ' . $stderr]);
            exit;
        }

        $data = json_decode($stdout, true);
        cacheSet('video_' . $id, $data);
        echo json_encode($data);
        break;

    case '/stream':
        $id = $_GET['id'] ?? '';
        if (!isValidVideoId($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid video ID']);
            exit;
        }

        $cmd = YT_DLP . " --no-update -g -f best " . escapeshellarg("https://www.youtube.com/watch?v=" . $id);
        $stdout = '';
        $stderr = '';
        $code = runCommand($cmd, $stdout, $stderr);

        $streamUrl = trim($stdout);
        if ($code === 0 && strpos($streamUrl, 'http') === 0) {
            header("Location: " . $streamUrl);
            exit;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Stream not found: ' . $stderr]);
        break;

    case '/download/mp4':
        $id = $_GET['id'] ?? '';
        $quality = $_GET['quality'] ?? '720';
        if (!isValidVideoId($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid video ID']);
            exit;
        }

        $height = in_array($quality, ['360', '480', '720', '1080']) ? $quality : '720';
        $format = "bestvideo[ext=mp4][height<={$height}]+bestaudio[ext=m4a]/best[ext=mp4]/best";

        $tempDir = __DIR__ . '/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempFile = $tempDir . '/' . $id . '_' . $height . 'p_' . time() . '.mp4';

        // Download to temp file
        $cmd = YT_DLP . " --no-update -f " . escapeshellarg($format) . " -o " . escapeshellarg($tempFile) . " " . escapeshellarg("https://www.youtube.com/watch?v=" . $id);
        
        $stdout = '';
        $stderr = '';
        $code = runCommand($cmd, $stdout, $stderr);

        if ($code === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: video/mp4');
            header('Content-Disposition: attachment; filename="' . $id . '_' . $height . 'p.mp4"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tempFile));
            
            if (ob_get_level()) {
                ob_end_clean();
            }
            readfile($tempFile);
            unlink($tempFile);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Download failed: ' . $stderr]);
            if (file_exists($tempFile)) unlink($tempFile);
            exit;
        }
        break;

    case '/download/mp3':
        $id = $_GET['id'] ?? '';
        $quality = $_GET['quality'] ?? '192';
        if (!isValidVideoId($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid video ID']);
            exit;
        }

        $kbps = in_array($quality, ['128', '192', '320']) ? $quality : '192';

        $tempDir = __DIR__ . '/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // We output as temp template. yt-dlp with -x will create a file with .mp3 extension automatically
        $tempTemplate = $tempDir . '/' . $id . '_' . $kbps . 'k_' . time();
        $expectedMp3 = $tempTemplate . '.mp3';

        // Command to download and extract audio
        $cmd = YT_DLP . " --no-update -x --audio-format mp3 --audio-quality " . escapeshellarg($kbps . "K") . " -o " . escapeshellarg($tempTemplate . '.%(ext)s') . " " . escapeshellarg("https://www.youtube.com/watch?v=" . $id);
        
        $stdout = '';
        $stderr = '';
        $code = runCommand($cmd, $stdout, $stderr);

        if ($code === 0 && file_exists($expectedMp3) && filesize($expectedMp3) > 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: audio/mpeg');
            header('Content-Disposition: attachment; filename="' . $id . '_' . $kbps . 'kbps.mp3"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($expectedMp3));
            
            if (ob_get_level()) {
                ob_end_clean();
            }
            readfile($expectedMp3);
            unlink($expectedMp3);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Audio download failed: ' . $stderr]);
            if (file_exists($expectedMp3)) unlink($expectedMp3);
            exit;
        }
        break;

    case '/suggest':
        $q = $_GET['q'] ?? '';
        if (empty($q)) {
            echo json_encode([]);
            exit;
        }
        $url = "https://suggestqueries.google.com/complete/search?client=firefox&ds=yt&q=" . urlencode($q);
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data[1])) {
                echo json_encode($data[1]);
                exit;
            }
        }
        echo json_encode([]);
        exit;

    case '/history':
        $db = getMockDb();
        echo json_encode($db['history']);
        break;

    case '/favorites':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $video = $input['video'] ?? null;
            if (!$video || !isset($video['id']) || !isValidVideoId($video['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid video object']);
                exit;
            }

            $db = getMockDb();
            $exists = false;
            foreach ($db['favorites'] as $fav) {
                if ($fav['id'] === $video['id']) { $exists = true; break; }
            }
            if (!$exists) {
                $db['favorites'][] = $video;
                saveMockDb($db);
            }
            echo json_encode(['success' => true, 'favorites' => $db['favorites']]);
        } else {
            $db = getMockDb();
            echo json_encode($db['favorites']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
