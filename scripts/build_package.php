<?php
/**
 * Build and package script for Australia Post Dolibarr Plugin
 * Creates a ready-to-deploy zip archive for Dolibarr's external module installer.
 *
 * Usage: php scripts/build_package.php
 */

$moduleName = 'auspost';
$rootDir = realpath(__DIR__ . '/..');
$descriptorFile = $rootDir . '/core/modules/modAuspost.class.php';

// Detect version: check CLI argument first, then fallback to descriptor
$version = '1.0.0';
if ($argc > 1 && !empty($argv[1])) {
    $version = ltrim(trim($argv[1]), 'v');
    // Call set_version.php to synchronize module files before packaging
    $setVersionScript = __DIR__ . '/set_version.php';
    if (file_exists($setVersionScript)) {
        passthru(escapeshellcmd('php ' . escapeshellarg($setVersionScript) . ' ' . escapeshellarg($version)));
    }
} elseif (file_exists($descriptorFile)) {
    $content = file_get_contents($descriptorFile);
    if (preg_match('/\$this->version\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        $version = $matches[1];
    }
}

echo "Building Dolibarr Module: {$moduleName} (v{$version})...\n";

$distDir = $rootDir . '/dist';
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

$zipFileName = "module_{$moduleName}-{$version}.zip";
$zipFilePath = $distDir . '/' . $zipFileName;

if (file_exists($zipFilePath)) {
    unlink($zipFilePath);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Error: PHP ZipArchive extension is required to package the module.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Error: Failed to create zip file at: {$zipFilePath}\n");
    exit(1);
}

// Folders and files to include
$includedDirs = array('admin', 'ajax', 'class', 'core', 'css', 'img', 'js', 'langs', 'lib', 'sql');
$includedFiles = array('calculator.php', 'README.md', 'LICENSE', 'ChangeLog.md');

$fileCount = 0;

// Add directories recursively
foreach ($includedDirs as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    if (!is_dir($fullPath)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $subPath = substr($item->getPathname(), strlen($rootDir) + 1);
        $zipEntryPath = $moduleName . '/' . str_replace('\\', '/', $subPath);

        if ($item->isDir()) {
            $zip->addEmptyDir($zipEntryPath);
        } elseif ($item->isFile()) {
            $zip->addFile($item->getPathname(), $zipEntryPath);
            $fileCount++;
        }
    }
}

// Add root files
foreach ($includedFiles as $file) {
    $fullPath = $rootDir . '/' . $file;
    if (file_exists($fullPath)) {
        $zipEntryPath = $moduleName . '/' . $file;
        $zip->addFile($fullPath, $zipEntryPath);
        $fileCount++;
    }
}

$zip->close();

$sizeKb = round(filesize($zipFilePath) / 1024, 2);
echo "Successfully packaged {$fileCount} files into:\n";
echo "  -> {$zipFilePath} ({$sizeKb} KB)\n";
echo "This package can now be deployed via Dolibarr's 'Deploy an external module' menu.\n";
exit(0);
