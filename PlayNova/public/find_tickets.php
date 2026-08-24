<?php
header("Content-Type: text/plain; charset=utf-8");
$base = dirname(__DIR__);
$needles = ['->tickets(', '->notifications(', 'User::'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    if (!preg_match('/\.(php|blade\.php)$/', $path)) continue;
    $rel = str_replace($base.'/', '', $path);
    if (str_starts_with($rel, 'vendor/')) continue;
    $c = file_get_contents($path);
    if (str_contains($c, '->tickets(') || str_contains($c, 'user->tickets')) echo "$rel\n";
}