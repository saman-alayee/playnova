<?php
header("Content-Type: text/plain; charset=utf-8");
$base = dirname(__DIR__);
$methods = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    if (!preg_match('/\.(php|blade\.php)$/', $path)) continue;
    if (str_contains($path, '/vendor/')) continue;
    $c = file_get_contents($path);
    if (preg_match_all('/(?:Auth::user\(\)|\$user)->([a-zA-Z_]+)\(/', $c, $m)) {
        foreach ($m[1] as $method) {
            if (in_array($method, ['save','delete','update','refresh','load','notify','toArray','getAttribute','setAttribute'])) continue;
            $methods[$method] = ($methods[$method] ?? 0) + 1;
        }
    }
}
$userPhp = file_get_contents($base.'/app/Models/User.php');
foreach ($methods as $method => $count) {
    if (!preg_match('/function\s+'.$method.'\s*\(/', $userPhp)) {
        echo "MISSING $method (used $count times)\n";
    }
}