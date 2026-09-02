<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRecommendationsRequest;
use App\Http\Resources\RecommendationResource;
use App\Models\User;
use App\Services\RecommendationService;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $recommendationService) {}

    public function index(User $user, UserRecommendationsRequest $request)
    {
        $data = $request->validated();

        $recommendations = $this->recommendationService->forUser($user, $data['store_id'] ?? null);

        return RecommendationResource::collection($recommendations);
    }
}
