<?php
/**
 * Version population script for semantic-release
 * Updates the version in modAuspost.class.php and composer.json
 *
 * Usage: php scripts/set_version.php <version>
 */

if ($argc < 2) {
    fwrite(STDERR, "Error: Missing version argument.\nUsage: php scripts/set_version.php <version>\n");
    exit(1);
}

$rawVersion = trim($argv[1]);
// Strip leading 'v' if present (e.g. v1.2.3 -> 1.2.3)
$version = ltrim($rawVersion, 'v');

$rootDir = realpath(__DIR__ . '/..');

// 1. Update core/modules/modAuspost.class.php
$descriptorFile = $rootDir . '/core/modules/modAuspost.class.php';
if (file_exists($descriptorFile)) {
    $content = file_get_contents($descriptorFile);
    $updatedContent = preg_replace(
        '/(\$this->version\s*=\s*[\'"])([^\'"]+)([\'"];)/',
        '${1}' . $version . '${3}',
        $content
    );

    if ($updatedContent !== null && $updatedContent !== $content) {
        file_put_contents($descriptorFile, $updatedContent);
        echo "Updated modAuspost.class.php version to {$version}\n";
    } else {
        echo "Note: modAuspost.class.php version pattern already matches or was not replaced.\n";
    }
} else {
    fwrite(STDERR, "Warning: Descriptor file not found at: {$descriptorFile}\n");
}

// 2. Update composer.json
$composerFile = $rootDir . '/composer.json';
if (file_exists($composerFile)) {
    $composerData = json_decode(file_get_contents($composerFile), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($composerData)) {
        $composerData['version'] = $version;
        file_put_contents($composerFile, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        echo "Updated composer.json version to {$version}\n";
    }
}

echo "Version successfully populated: {$version}\n";
exit(0);
