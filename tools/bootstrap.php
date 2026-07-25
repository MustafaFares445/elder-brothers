<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root.'/routes/api.php') && is_file($root.'/artisan')) {
    fwrite(STDOUT, "Laravel source is already extracted.\n");
    exit(0);
}

$parts = glob(__DIR__.'/payload.*') ?: [];
sort($parts, SORT_NATURAL);

if ($parts === []) {
    fwrite(STDERR, "Laravel bootstrap payload was not found.\n");
    exit(1);
}

$encoded = '';
foreach ($parts as $part) {
    $encoded .= trim((string) file_get_contents($part));
}

$archive = base64_decode($encoded, true);
if ($archive === false) {
    fwrite(STDERR, "Laravel bootstrap payload is invalid.\n");
    exit(1);
}

$temp = tempnam(sys_get_temp_dir(), 'elder-brothers-');
if ($temp === false) {
    fwrite(STDERR, "Unable to create a temporary archive.\n");
    exit(1);
}

$archivePath = $temp.'.tar.gz';
rename($temp, $archivePath);
file_put_contents($archivePath, $archive);

$command = sprintf(
    'tar -xzf %s -C %s 2>&1',
    escapeshellarg($archivePath),
    escapeshellarg($root),
);

exec($command, $output, $exitCode);
@unlink($archivePath);

if ($exitCode !== 0) {
    fwrite(STDERR, "Unable to extract Laravel source:\n".implode("\n", $output)."\n");
    exit($exitCode);
}

fwrite(STDOUT, "Elder Brothers Laravel application extracted successfully.\n");
