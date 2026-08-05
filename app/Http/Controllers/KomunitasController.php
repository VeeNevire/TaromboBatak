<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class KomunitasController extends Controller
{
    /**
     * Show the public komunitas page.
     */
    public function index(): Response
    {
        return Inertia::render('komunitas/index');
    }
}
