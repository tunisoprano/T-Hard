<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchProductsRequest;
use App\Services\SemanticSearchService;

class SearchController extends Controller
{
    public function index(SearchProductsRequest $request, SemanticSearchService $search)
    {
        $data = $request->validated();

        return response()->json([
            'data' => $search->search($data['q'], $data['store_id'] ?? null),
        ]);
    }
}
