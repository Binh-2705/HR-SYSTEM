<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

use App\Services\InternalApiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private InternalApiClient $client) {}

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('q', ''));
        $results = [];

        if ($keyword !== '') {
            $response = $this->client->get('biz/search', ['q' => $keyword]);
            $results  = (array) ($response['results'] ?? []);
        }

        return view('search.index', [
            'keyword' => $keyword,
            'results' => $results,
        ]);
    }
}
