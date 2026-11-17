<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Worker::query()->orderBy('last_name')->orderBy('first_name');
        if ($request->boolean('active_only')) {
            $query->where('active', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        return response()->json(Worker::create($data), 201);
    }

    public function show(Worker $worker): JsonResponse
    {
        return response()->json($worker);
    }

    public function update(Request $request, Worker $worker): JsonResponse
    {
        $data = $request->validate($this->rules(partial: true));
        $worker->update($data);

        return response()->json($worker);
    }

    public function destroy(Worker $worker): Response
    {
        $worker->delete();

        return response()->noContent();
    }

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'first_name' => [$req, 'string', 'max:80'],
            'last_name' => [$req, 'string', 'max:80'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
