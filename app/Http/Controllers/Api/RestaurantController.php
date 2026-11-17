<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RestaurantController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Restaurant::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug;
        $n = 2;
        while (Restaurant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        $restaurant = Restaurant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'color' => $data['color'] ?? '#0f172a',
        ]);

        return response()->json($restaurant, 201);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        return response()->json($restaurant);
    }

    public function update(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'slug' => ['sometimes', 'required', 'alpha_dash', Rule::unique('restaurants')->ignore($restaurant->id)],
        ]);

        $restaurant->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json($restaurant);
    }

    public function destroy(Restaurant $restaurant): Response
    {
        $restaurant->delete();

        return response()->noContent();
    }
}
