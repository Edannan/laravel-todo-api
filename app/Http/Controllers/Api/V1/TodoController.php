<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTodoRequest;
use App\Http\Requests\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TodoController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'search' => ['sometimes', 'string', 'max:255'],
            'due_date_from' => ['sometimes', 'date_format:Y-m-d'],
            'due_date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:due_date_from'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date', 'after_or_equal:created_from'],
            'sort_by' => ['sometimes', 'in:created_at,updated_at,due_date,title,priority,status'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Todo::query();

        $query->when(isset($validated['status']), fn ($q) =>
            $q->where('status', $validated['status'])
        );

        $query->when(isset($validated['priority']), fn ($q) =>
            $q->where('priority', $validated['priority'])
        );

        $query->when(isset($validated['search']), function ($q) use ($validated) {
            $term = trim($validated['search']);
            if ($term !== '') {
                $q->where(function ($subQuery) use ($term) {
                    $subQuery->where('title', 'ILIKE', "%{$term}%")
                        ->orWhere('description', 'ILIKE', "%{$term}%");
                });
            }
        });

        $query->when(isset($validated['due_date_from']), fn ($q) =>
            $q->whereDate('due_date', '>=', $validated['due_date_from'])
        );

        $query->when(isset($validated['due_date_to']), fn ($q) =>
            $q->whereDate('due_date', '<=', $validated['due_date_to'])
        );

        $query->when(isset($validated['created_from']), fn ($q) =>
            $q->where('created_at', '>=', Carbon::parse($validated['created_from']))
        );

        $query->when(isset($validated['created_to']), fn ($q) =>
            $q->where('created_at', '<=', Carbon::parse($validated['created_to']))
        );

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        $todos = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return TodoResource::collection($todos);
    }

    public function store(StoreTodoRequest $request)
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'pending';
        $data['priority'] = $data['priority'] ?? 'medium';

        if ($data['status'] === 'completed') {
            $data['completed_at'] = now();
        }

        $todo = Todo::create($data);

        return (new TodoResource($todo))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Todo $todo): TodoResource
    {
        return new TodoResource($todo);
    }

    public function update(UpdateTodoRequest $request, Todo $todo): TodoResource
    {
        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $data['completed_at'] = $data['status'] === 'completed'
                ? ($todo->completed_at ?? now())
                : null;
        }

        $todo->update($data);

        return new TodoResource($todo->refresh());
    }

    public function destroy(Todo $todo)
    {
        $todo->delete();

        return response()->json([
            'message' => 'Todo deleted successfully.',
        ]);
    }
}
