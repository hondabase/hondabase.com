<?php
declare(strict_types=1);

require __DIR__ . '/inc/boot.php';

// Connect as root to bypass the read-only restrictions on the pgmfi_web user
$pdo = new PDO('mysql:host=localhost;dbname=pgmfi_forum_archive;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);


// Select all media that are not complete
$stmt = $pdo->query("SELECT media_id, original_url, archive_timestamp FROM media WHERE state != 'complete'");
$rows = $stmt->fetchAll();

echo "Found " . count($rows) . " media items to recover.\n";

$completeCount = 0;
$failedCount = 0;

foreach ($rows as $row) {
    $mediaId = (int)$row['media_id'];
    $url = $row['original_url'];
    $timestamp = $row['archive_timestamp'];

    echo "\nProcessing ID {$mediaId}: {$url}\n";

    // 1. Check Wayback Machine API
    $waybackUrl = "https://archive.org/wayback/available?url=" . urlencode($url);
    if ($timestamp) {
        $waybackUrl .= "&timestamp=" . urlencode($timestamp);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $waybackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        echo "  - Wayback API request failed.\n";
        updateState($mediaId, 'error', 'Wayback API request failed');
        $failedCount++;
        continue;
    }

    $data = json_decode((string)$response, true);
    $snapshot = $data['archived_snapshots']['closest'] ?? null;

    if (!$snapshot || !$snapshot['available']) {
        echo "  - No archived snapshot available.\n";
        updateState($mediaId, 'missing', 'no archived capture');
        $failedCount++;
        continue;
    }

    $archiveUrl = $snapshot['url'];
    $archiveTimestamp = $snapshot['timestamp'];
    echo "  - Found snapshot: {$archiveUrl} (Timestamp: {$archiveTimestamp})\n";

    // 2. Download the file from Wayback Machine
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $archiveUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $fileData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode !== 200 || !$fileData) {
        echo "  - Failed to download file (HTTP {$httpCode}).\n";
        updateState($mediaId, 'error', "Download failed: HTTP {$httpCode}");
        $failedCount++;
        continue;
    }

    // Basic validation: make sure it is not an HTML error page (Wayback often returns HTML for missing/error pages)
    if (str_contains(strtolower((string)$contentType), 'text/html') && str_contains(strtolower($fileData), '<html')) {
        echo "  - Downloaded content appears to be HTML (Wayback error page).\n";
        updateState($mediaId, 'missing', 'archived page is HTML error');
        $failedCount++;
        continue;
    }

    // 3. Process the file
    $sha256 = hash('sha256', $fileData);
    $size = strlen($fileData);

    // Determine extension
    $ext = 'jpg';
    if ($contentType) {
        $cleanMime = strtolower(explode(';', $contentType)[0]);
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'text/plain' => 'txt',
        ];
        if (isset($mimeMap[$cleanMime])) {
            $ext = $mimeMap[$cleanMime];
        } else {
            $urlExt = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
            if ($urlExt) {
                $ext = strtolower($urlExt);
            }
        }
    } else {
        $urlExt = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        if ($urlExt) {
            $ext = strtolower($urlExt);
        }
    }

    $dir = substr($sha256, 0, 2);
    $filename = "{$sha256}.{$ext}";
    $localPath = "{$dir}/{$filename}";

    // Ensure output directory exists
    $targetDir = __DIR__ . "/media/{$dir}";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetFile = "{$targetDir}/{$filename}";
    if (file_put_contents($targetFile, $fileData) === false) {
        echo "  - Failed to write file to disk.\n";
        updateState($mediaId, 'error', 'failed to write to disk');
        $failedCount++;
        continue;
    }

    // 4. Update Database
    $upd = $pdo->prepare("
        UPDATE media 
        SET state = 'complete',
            archive_timestamp = ?,
            archive_original_url = ?,
            mime_type = ?,
            byte_length = ?,
            sha256 = ?,
            local_path = ?,
            last_error = NULL,
            attempts = attempts + 1
        WHERE media_id = ?
    ");
    $upd->execute([
        $archiveTimestamp,
        $url,
        $contentType ?: 'image/jpeg',
        $size,
        $sha256,
        $localPath,
        $mediaId
    ]);

    echo "  - Successfully recovered! Saved to: media/{$localPath}\n";
    $completeCount++;
}

echo "\nRecovery run complete. Recovered: {$completeCount}, Failed: {$failedCount}\n";

function updateState(int $mediaId, string $state, string $error): void {
    global $pdo;
    $upd = $pdo->prepare("
        UPDATE media 
        SET state = ?, 
            last_error = ?,
            attempts = attempts + 1
        WHERE media_id = ?
    ");
    $upd->execute([$state, $error, $mediaId]);
}
