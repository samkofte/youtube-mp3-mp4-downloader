<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

$ytDlpBin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? __DIR__ . '/bin/yt-dlp.exe' : __DIR__ . '/bin/yt-dlp';
$ffmpegBin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? __DIR__ . '/bin/ffmpeg.exe' : __DIR__ . '/bin/ffmpeg';
$tempDir = __DIR__ . '/temp';

if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Otomatik Temizleme (Garbage Collection): 24 saatten eski geçici dosyaları ve önbellekleri sil
// Sunucuda yer kaplamasını %100 engeller (kullanıcı indirmeyi yarıda keserse kalan .part dosyaları vs için)
if (rand(1, 10) === 1) { // %10 ihtimalle çalışır, sunucuyu yormaz
    $dirsToClean = [$tempDir, __DIR__ . '/cache'];
    $expireTime = time() - (24 * 3600); // 24 saat

    foreach ($dirsToClean as $dir) {
        if (file_exists($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $expireTime) {
                    @unlink($file);
                }
            }
        }
    }
}

$pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$uri = $_SERVER['REQUEST_URI'];

$endpoint = '';
if (strpos($pathInfo, '/search') === 0 || strpos($uri, '/search') !== false) {
    $endpoint = 'search';
} elseif (strpos($pathInfo, '/video') === 0 || strpos($uri, '/video') !== false) {
    $endpoint = 'video';
} elseif (strpos($pathInfo, '/download/mp4') === 0 || strpos($uri, '/download/mp4') !== false) {
    $endpoint = 'download_mp4';
} elseif (strpos($pathInfo, '/download/mp3') === 0 || strpos($uri, '/download/mp3') !== false) {
    $endpoint = 'download_mp3';
} elseif (strpos($pathInfo, '/stream') === 0 || strpos($uri, '/stream') !== false) {
    $endpoint = 'stream';
}

function runYtDlp($args) {
    global $ytDlpBin;
    $cmd = escapeshellcmd($ytDlpBin) . ' ' . implode(' ', array_map('escapeshellarg', $args));
    return shell_exec($cmd);
}

if ($endpoint === 'search') {
    $q = $_GET['q'] ?? '';
    if (empty($q)) {
        http_response_code(400);
        echo json_encode(['error' => 'Query is required']);
        exit;
    }

    $cacheDir = __DIR__ . '/cache';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/search_' . md5($q) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) { // 1 hour cache
        header('Content-Type: application/json');
        readfile($cacheFile);
        exit;
    }

    $output = runYtDlp(['ytsearch15:' . $q, '--dump-json', '--no-playlist', '--flat-playlist', '--ignore-errors']);
    $results = [];
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (empty($line)) continue;
        $data = json_decode($line, true);
        if ($data) {
            $results[] = [
                'id' => $data['id'],
                'title' => $data['title'],
                'thumbnail' => $data['thumbnails'][0]['url'] ?? ($data['thumbnail'] ?? ''),
                'duration' => $data['duration_string'] ?? '',
                'author' => $data['uploader'] ?? ''
            ];
        }
    }
    
    $json = json_encode($results);
    file_put_contents($cacheFile, $json);
    echo $json;
    exit;
}

if ($endpoint === 'video') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Video ID is required']);
        exit;
    }

    $cacheDir = __DIR__ . '/cache';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/video_' . md5($id) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        header('Content-Type: application/json');
        readfile($cacheFile);
        exit;
    }

    $output = runYtDlp(['https://www.youtube.com/watch?v=' . $id, '--dump-json', '--no-playlist']);
    $data = json_decode($output, true);
    if (!$data) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch video info']);
        exit;
    }

    $formats = [];
    foreach ($data['formats'] as $f) {
        if (isset($f['vcodec']) && $f['vcodec'] !== 'none') {
            $formats[] = [
                'format_id' => $f['format_id'],
                'quality' => $f['height'] . 'p',
                'ext' => $f['ext'],
                'hasAudio' => (isset($f['acodec']) && $f['acodec'] !== 'none'),
                'filesize' => $f['filesize'] ?? 0
            ];
        }
    }

    $response = [
        'id' => $data['id'],
        'title' => $data['title'],
        'thumbnail' => $data['thumbnail'],
        'duration' => $data['duration_string'] ?? '',
        'author' => $data['uploader'] ?? '',
        'view_count' => $data['view_count'] ?? 0,
        'description' => $data['description'] ?? '',
        'channel_url' => $data['channel_url'] ?? '',
        'formats' => array_values(array_filter($formats, function($f) {
            return in_array($f['quality'], ['144p', '240p', '360p', '480p', '720p', '1080p']);
        }))
    ];
    
    $json = json_encode($response);
    file_put_contents($cacheFile, $json);
    echo $json;
    exit;
}

if ($endpoint === 'stream') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo "Video ID required";
        exit;
    }
    
    // Get best mp4 url with audio
    $output = runYtDlp([
        'https://www.youtube.com/watch?v=' . $id,
        '-f', 'best[ext=mp4]',
        '--get-url'
    ]);
    
    $url = trim($output);
    if (empty($url)) {
        http_response_code(500);
        echo "Failed to extract stream URL";
        exit;
    }

    // Proxy the stream to bypass 403
    header('Content-Type: video/mp4');
    header('Cache-Control: no-cache');
    header('Accept-Ranges: bytes');
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
        ]
    ]);
    
    $fp = fopen($url, 'rb', false, $context);
    if ($fp) {
        fpassthru($fp);
        fclose($fp);
    }
    exit;
}

if ($endpoint === 'download_mp4') {
    $id = $_GET['id'] ?? '';
    $quality = str_replace('p', '', $_GET['quality'] ?? '720');
    
    if (empty($id)) {
        die("Video ID required");
    }

    $title = "video_{$id}";
    $cacheFile = __DIR__ . '/cache/video_' . md5($id) . '.json';
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && !empty($data['title'])) {
            $title = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $data['title']);
            $title = trim(substr($title, 0, 50));
        }
    }
    $rand = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 5);
    $downloadName = "{$title}_{$quality}p_{$rand}.mp4";

    $format = "bestvideo[height<={$quality}][ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best";
    $tempFile = $tempDir . "/{$id}_{$quality}p_" . time() . ".mp4";
    
    $cmd = escapeshellcmd($ytDlpBin) . " --ffmpeg-location " . escapeshellarg($ffmpegBin) . " -f " . escapeshellarg($format) . " -o " . escapeshellarg($tempFile) . " " . escapeshellarg("https://www.youtube.com/watch?v=" . $id);
    shell_exec($cmd);

    if (file_exists($tempFile)) {
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
    } else {
        die("Failed to download video.");
    }
    exit;
}

if ($endpoint === 'download_mp3') {
    $id = $_GET['id'] ?? '';
    $quality = str_replace('k', '', $_GET['quality'] ?? '128');
    
    if (empty($id)) {
        die("Video ID required");
    }

    $title = "audio_{$id}";
    $cacheFile = __DIR__ . '/cache/video_' . md5($id) . '.json';
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && !empty($data['title'])) {
            $title = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $data['title']);
            $title = trim(substr($title, 0, 50));
        }
    }
    $rand = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 5);
    $downloadName = "{$title}_{$quality}kbps_{$rand}.mp3";

    $expectedMp3 = $tempDir . "/{$id}_{$quality}k_" . time() . ".mp3";
    
    $cmd = escapeshellcmd($ytDlpBin) . " --ffmpeg-location " . escapeshellarg($ffmpegBin) . " -x --audio-format mp3 --audio-quality " . escapeshellarg($quality . "K") . " -o " . escapeshellarg($expectedMp3) . " " . escapeshellarg("https://www.youtube.com/watch?v=" . $id);
    shell_exec($cmd);

    if (file_exists($expectedMp3)) {
        header('Content-Type: audio/mpeg');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($expectedMp3));
        readfile($expectedMp3);
        unlink($expectedMp3);
    } else {
        die("Failed to download audio.");
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'API endpoint not found']);
