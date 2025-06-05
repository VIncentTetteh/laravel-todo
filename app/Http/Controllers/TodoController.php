<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TodoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Todo::query()->with('user');

        if ($request->input('completed')) {
            $query->where('completed', true);
        }

        if ($request->input('pending')) {
            $query->where('completed', false);
        }

        if ($sortBy = $request->input('sort_by')) {
            $direction = $request->input('sort_direction', 'asc');
            $query->orderBy($sortBy, $direction);
        }

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        }

        $todos = $query->paginate($request->input('per_page', 10));

        return response()->json($todos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'completed' => 'boolean',
        ]);

        $todo = Todo::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'completed' => $validated['completed'] ?? false,
        ]);

        return response()->json($todo, 201);
    }

    public function show($id): JsonResponse
    {
        $todo = Todo::with('user')->findOrFail($id);

        if ($todo->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($todo);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'completed' => 'boolean',
        ]);

        $todo = Todo::with('user')->findOrFail($id);

        if ($todo->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->update($validated);

        return response()->json($todo);
    }

    public function destroy($id): JsonResponse
    {
        $todo = Todo::with('user')->findOrFail($id);

        if ($todo->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->delete();

        return response()->json(['message' => 'Todo deleted successfully'], 204);
    }

    public function markAsCompleted($id): JsonResponse
    {
        $todo = Todo::with('user')->findOrFail($id);

        if ($todo->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->update(['completed' => true]);

        return response()->json($todo);
    }

    public function markAsPending($id): JsonResponse
    {
        $todo = Todo::with('user')->findOrFail($id);

        if ($todo->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todo->update(['completed' => false]);

        return response()->json($todo);
    }

    public function userTodos(Request $request, $userId): JsonResponse
    {
        $todos = Todo::where('user_id', $userId)
            ->with('user')
            ->paginate($request->input('per_page', 10));

        return response()->json($todos);
    }

    public function searchTodos(Request $request): JsonResponse
    {
        $searchTerm = $request->input('search');

        $todos = Todo::where(function ($query) use ($searchTerm) {
            $query->where('title', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%");
        })
        ->where('user_id', auth()->id())
        ->with('user')
        ->paginate($request->input('per_page', 10));

        return response()->json($todos);
    }
}
