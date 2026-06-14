<?php
require_once('/var/www/tracker.php');
track_page_view('Reference Material index');

$currentPath = getcwd();
$requestedPath = isset($_GET['path']) ? $_GET['path'] : '';

// Security: Validate and sanitize the path
$fullPath = realpath($currentPath . DIRECTORY_SEPARATOR . $requestedPath);
if ($fullPath === false || strpos($fullPath, $currentPath) !== 0) $fullPath = $currentPath;

$items = scandir($fullPath);

$formatSize = fn($bytes) => 
    ($units = ['B', 'KB', 'MB', 'GB', 'TB']) &&
    ($bytes = max($bytes, 0)) &&
    ($pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1)) ?
    round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow] : '0 B';

$formatLastModified = fn($timestamp) => date('Y-m-d H:i:s', $timestamp);

// Function to get relative web path from current directory
$getWebPath = fn($path) => ltrim(str_replace($currentPath, '', $path), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reference Material - hondabase.com</title>

    <meta name="description" content="Reference material and documentation for Honda vehicles database">
    <meta name="keywords" content="honda, database, reference, documentation, vehicles, cars">
    <meta name="author" content="hondabase.com">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Reference Material - hondabase.com">
    <meta property="og:description" content="Reference material and documentation for Honda vehicles database">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.hondabase.com/reference">

    <link rel="stylesheet" href="../base-styles.css">
    <style>
        main {
            padding: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            background: var(--card-bg-light);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: var(--background-light);
            border-bottom: 2px solid var(--border-light);
            color: var(--text-muted-light);
            font-weight: 500;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-light);
            font-size: 0.875rem;
        }

        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        td[role="icon"] {
            font-size: 1.25rem;
        }

        td[role="name"] a {
            font-weight: 500;
            display: block;
            text-decoration: none;
        }

        td[role="meta"] {
            color: var(--text-muted-light);
        }

        nav {
            margin: 1rem 0;
            color: var(--text-muted-light);
        }

        nav a {
            text-decoration: none;
        }

        nav span {
            margin: 0 0.5rem;
        }

        @media (max-width: 768px) {
            main {
                padding: 1rem;
            }

            th, td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <main>
        <header>
            <h1>Hondabase Reference Explorer</h1>
            <?php
            $relativePath = $getWebPath($fullPath);
            $pathParts = array_filter(explode(DIRECTORY_SEPARATOR, $relativePath));
            $breadcrumb = '<nav><a href=".">current directory</a>';
            $currentBreadcrumb = '.';
            foreach ($pathParts as $part) {
                $currentBreadcrumb .= '/' . $part;
                $breadcrumb .= '<span>/</span><a href="' . $currentBreadcrumb . '">' . htmlspecialchars($part) . '</a>';
            }
            $breadcrumb .= '</nav>';
            echo $breadcrumb;
            ?>
        </header>

        <div role="region" aria-label="File listing" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Name</th>
                        <th scope="col">Size</th>
                        <th scope="col">Last Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        if ($item === '.' || $item === 'index.php') continue;
                        $itemPath = $fullPath . DIRECTORY_SEPARATOR . $item;
                        $isDir = is_dir($itemPath);
                        $icon = $isDir ? '📁' : '📄';
                        $webPath = $item === '..' ? '..' : $getWebPath($itemPath);
                        ?>
                        <tr>
                            <td role="icon"><?= $icon ?></td>
                            <td role="name">
                                <a href="<?= $webPath ?>"><?= htmlspecialchars($item) ?></a>
                            </td>
                            <td role="meta">
                                <?= !$isDir && $item !== '..' ? $formatSize(filesize($itemPath)) : '-' ?>
                            </td>
                            <td role="meta"><?= $formatLastModified(filemtime($itemPath)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer><a href="https://www.hondabase.com">Hondabase</a> - Community-Driven Honda Knowledgebase</footer>
    </main>
</body>
</html>