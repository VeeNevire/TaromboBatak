<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TrafficMonitorController extends Controller
{
    /** Display the administrator-only embedded Google Analytics report. */
    public function index(): Response
    {
        return Inertia::render('traffic-monitor/index', [
            'reportUrl' => config('services.google.analytics_report_embed_url'),
        ]);
    }
}
