<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TentangController extends Controller
{
    /**
     * Show the public tentang (about) page.
     */
    public function index(): Response
    {
        return Inertia::render('tentang/index');
    }
}
