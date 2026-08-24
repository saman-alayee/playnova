<?php
header("Content-Type: text/plain; charset=utf-8");
$base = dirname(__DIR__);
require $base . "/vendor/autoload.php";
$app = require_once $base . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "=== Users ===\n";
foreach (User::select('id','username','email','is_admin')->get() as $u) {
    echo "id=$u->id user=$u->username email=$u->email admin=".($u->is_admin?1:0)."\n";
}

echo "\n=== Login tests ===\n";
Auth::logout();
foreach ([
    ['email','admin@admin.com','PlayNova@Admin2026!'],
    ['email','admin@admin.com','password'],
    ['username','admin','PlayNova@Admin2026!'],
] as [$field,$val,$pass]) {
    $ok = Auth::attempt([$field=>$val,'password'=>$pass]);
    echo "$field=$val pass=".substr($pass,0,8).'... : '.($ok?'OK':'FAIL')."\n";
    Auth::logout();
}

echo "\n=== Register simulation ===\n";
$uname = 'chkuser'.random_int(10000,99999);
try {
    $u = User::create([
        'name' => $uname,
        'username' => $uname,
        'email' => $uname.'@example.com',
        'password' => Hash::make('TestPass123'),
        'referral_code' => User::generateReferralCode(),
    ]);
    echo "create OK id={$u->id}\n";
    User::where('id',$u->id)->delete();
    echo "cleanup OK\n";
} catch (Throwable $e) {
    echo "create FAIL: ".$e->getMessage()."\n";
}