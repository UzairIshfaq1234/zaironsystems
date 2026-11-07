<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/** Serves the admin portal's React app; the portal handles its own routing and login. */
class ShellController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('admin');
    }
}
