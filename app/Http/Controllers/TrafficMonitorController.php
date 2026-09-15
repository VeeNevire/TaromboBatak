<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TrafficMonitorController extends Controller
{
    public function publicReport(): Response
    {
        return Inertia::render('traffic-monitor/public', [
            'reportUrl' => config('services.google.analytics_report_embed_url'),
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('traffic-monitor/index', [
            'reportUrl' => config('services.google.analytics_report_embed_url'),
        ]);
    }
}
