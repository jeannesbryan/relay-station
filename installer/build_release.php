<?php
// ==========================================================
// RELAY STATION: RELEASE BUILDER
// ==========================================================
// Builds both release archives from the working tree, so that packaging is a
// single repeatable command instead of a hand-assembled zip.
//
//   php installer/build_release.php
//
// Output:
//   installer/relay.zip                 the application (many files)
//   installer/relay-station-<ver>.zip   the deployable drop-pod:
//                                       install.php + schema.sql + relay.zip
//
// WHY THE INNER ARCHIVE IS REBUILT FROM A MANIFEST: install.php extracts
// relay.zip into its own directory, so the archive must contain exactly the
// runtime files and nothing else - no installer/, no tests/, no .git. The
// manifest below is the single place that list lives, and the builder asserts
// every entry exists before writing anything, so a rename cannot silently
// produce a short archive.
//
// Both outputs are gitignored: they are build artifacts, not source.

$ROOT = dirname(__DIR__);
$OUT  = __DIR__;

// --------------------------------------------------------------------------
// 1. Files that make up a running station.
// --------------------------------------------------------------------------
$manifest = [
    // entry points
    'index.php',
    'console.php',
    'bookmarks.php',
    'direct.php',
    'api_ping.php',
    'api_inbox.php',
    'api_handshake.php',
    'sw.js',
    'manifest.json',
    'version.json',
    '.htaccess',
    // shared core
    'core/security.php',
    'core/ssl_shield.php',
    'core/db_connect.php',
    'core/transmitter.php',
    'core/radar_sweep.php',
    'core/add_planet.php',
    'core/remove_planet.php',
    'core/alert_action.php',
    'core/telegram.php',
    'core/updater.php',
    // assets
    'assets/terminal.css',
    'assets/terminal.js',
    'assets/icon.svg',
    'assets/icon-192.png',
    'assets/icon-512.png',
];

// The manifest must cover every runtime .php outside installer/ and tests/,
// otherwise a new file would ship in the repo but never in the release.
$expected = [];
foreach (['core' => 'core/'] as $dir => $prefix) {
    foreach (glob($ROOT . '/' . $dir . '/*.php') as $f) {
        $expected[] = $prefix . basename($f);
    }
}
foreach (glob($ROOT . '/*.php') as $f) {
    $expected[] = basename($f);
}
// Every asset ships, so any asset not in the manifest is an omission. This is
// not theoretical: the first draft of this manifest dropped the two PWA icons,
// which manifest.json references by name - the station would have installed
// with a broken icon on every device.
foreach (glob($ROOT . '/assets/*') as $f) {
    $expected[] = 'assets/' . basename($f);
}

$missing = array_diff($expected, $manifest);
if ($missing) {
    fwrite(STDERR, "BUILD FAILED: these runtime files are not in the manifest:\n  "
        . implode("\n  ", $missing) . "\n");
    exit(1);
}

foreach ($manifest as $rel) {
    if (!is_file($ROOT . '/' . $rel)) {
        fwrite(STDERR, "BUILD FAILED: manifest lists a file that does not exist: $rel\n");
        exit(1);
    }
}

$version = json_decode(file_get_contents($ROOT . '/version.json'), true)['version'] ?? null;
if (!$version) {
    fwrite(STDERR, "BUILD FAILED: cannot read version from version.json\n");
    exit(1);
}

// --------------------------------------------------------------------------
// 2. Inner archive: the application.
// --------------------------------------------------------------------------
$innerPath = $OUT . '/relay.zip';
@unlink($innerPath);

$inner = new ZipArchive();
if ($inner->open($innerPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "BUILD FAILED: cannot create $innerPath\n");
    exit(1);
}
foreach ($manifest as $rel) {
    $inner->addFile($ROOT . '/' . $rel, $rel);
}
$inner->close();

// --------------------------------------------------------------------------
// 3. Outer archive: the deployable drop-pod.
// --------------------------------------------------------------------------
$outerPath = $OUT . '/relay-station-' . $version . '.zip';
@unlink($outerPath);

$outer = new ZipArchive();
if ($outer->open($outerPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "BUILD FAILED: cannot create $outerPath\n");
    exit(1);
}
foreach (['install.php', 'schema.sql'] as $rel) {
    $outer->addFile($OUT . '/' . $rel, $rel);
}
$outer->addFile($innerPath, 'relay.zip');
$outer->close();

// --------------------------------------------------------------------------
// 4. Report, and verify what we just wrote.
// --------------------------------------------------------------------------
$check = new ZipArchive();
$check->open($outerPath);
$innerFromOuter = $check->getFromName('relay.zip');
$check->close();

$innerMd5     = md5_file($innerPath);
$innerInOuter = md5($innerFromOuter);

printf("  version        : %s\n", $version);
printf("  relay.zip      : %s bytes, %d entries\n", number_format(filesize($innerPath)), count($manifest));
printf("  outer          : %s\n", basename($outerPath));
printf("  outer size     : %s bytes\n", number_format(filesize($outerPath)));
printf("  outer md5      : %s\n", md5_file($outerPath));
printf("  inner relay.zip: %s\n", $innerMd5);
printf("  nested copy    : %s  %s\n", $innerInOuter,
    $innerMd5 === $innerInOuter ? '(matches)' : '(MISMATCH!)');

if ($innerMd5 !== $innerInOuter) {
    fwrite(STDERR, "BUILD FAILED: nested relay.zip does not match the standalone one\n");
    exit(1);
}

// The wrapper must be removed by install.php on a completed install, so its
// name has to match the pattern that installer globs for.
$installerSrc = file_get_contents($OUT . '/install.php');
$patternOk = strpos($installerSrc, "'relay-station-*.zip'") !== false;
printf("  self-destruct  : %s\n", $patternOk
    ? 'install.php matches relay-station-*.zip'
    : 'WARNING: install.php does not match this archive name');

echo "BUILD OK\n";
exit($patternOk ? 0 : 1);
