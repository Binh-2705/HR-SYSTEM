<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\InternalApiClient;

use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;

class SystemHealthController extends Controller
{
    public function __construct(private InternalApiClient $client)
    {
    }

    public function index(): View
    {
        $response = $this->client->get('biz/system-health/status');

        return view('systemhealth.index', [
            'statuses' => (array) ($response['statuses'] ?? []),
            'botStatus' => (array) ($response['botStatus'] ?? ['status' => 'error', 'detail' => 'Unknown']),
            'botUrl' => (string) ($response['botUrl'] ?? ''),
        ]);
    }

    public function runChecks(): RedirectResponse
    {
        $response = $this->client->post('biz/system-health/run-checks');
        $failed = (int) ($response['failed'] ?? 0);

        return redirect()->route('systemhealth.index')->with(
            $failed > 0 ? 'error' : 'success',
            $failed > 0
                ? 'Health check hoàn tất với ' . $failed . ' lỗi.'
                : 'Health check hoàn tất: tất cả kiểm tra đều đạt.'
        );
    }
}