<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class BudayaController extends Controller
{
    /**
     * Show the public budaya page.
     */
    public function index(): Response
    {
        return Inertia::render('budaya/index');
    }
}
