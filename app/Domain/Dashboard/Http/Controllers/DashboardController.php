<?php

namespace App\Domain\Dashboard\Http\Controllers;

use App\Support\Auth;
use App\Support\Response;

class DashboardController
{
    public function index(): void
    {
        Auth::requireAdmin();

        Response::view('admin/dashboard/index', [
            'user' => Auth::user(),
        ]);
    }
}
