<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

abstract class BaseSeatAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('seat_admin');
    }
}
