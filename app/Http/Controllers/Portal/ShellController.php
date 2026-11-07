<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/** Serves the employee portal's React app; the app handles its own routing and sign-in. */
class ShellController extends Controller
{
    public function __invoke(): Response
    {
        return response()->view('portal');
    }
}
