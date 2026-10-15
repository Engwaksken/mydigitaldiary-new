<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DebtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->syncOverdue($request);

        $query = Debt::query()
            ->where('user_id', $request->user()->id);

        if (Schema::hasColumn('debts', 'is_archived')) {
            $query->where('is_archived', $request->boolean('archived'));
        }

        $type = $request->query('type');
        if (in_array($type, ['borrowed', 'lent'], true)) {
            $query->where('type', $type);
        }

        $search = trim((string) $request->query('search', $request->query('q', '')));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('person_name', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%');
            });
        }

        $items = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(max(1, min(50, (int) $request->integer('per_page', 10))))
            ->withQueryString();

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->owned($request, $id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $item = Debt::query()->create([
            ...$this->payload($request),
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Debt saved.',
            'data' => $item->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->owned($request, $id);
        $item->update($this->payload($request, $item));

        return response()->json([
            'message' => 'Debt updated.',
            'data' => $item->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->owned($request, $id)->delete();

        return response()->json(['message' => 'Debt deleted.']);
    }

    private function payload(Request $request, ?Debt $existing = null): array
    {
        $timezone = $request->user()->timezone
            ?: config('app.timezone', 'Africa/Kampala');

        $type = strtolower(trim((string) (
            $request->input('type')
            ?? $request->input('debt_type')
            ?? $existing?->type
            ?? 'borrowed'
        )));

        $status = strtolower(trim((string) (
            $request->input('status')
            ?? $existing?->status
            ?? 'outstanding'
        )));

        if (in_array($status, ['active', 'pending', 'open'], true)) {
            $status = 'outstanding';
        }

        $data = [
            'type' => in_array($type, ['borrowed', 'lent'], true)
                ? $type
                : 'borrowed',
            'person_name' => trim((string) (
                $request->input('person_name')
                ?? $request->input('name')
                ?? $existing?->person_name
                ?? ''
            )),
            'contact_email' => $request->has('contact_email')
                ? $request->input('contact_email')
                : $existing?->contact_email,
            'contact_phone' => $request->has('contact_phone')
                ? $request->input('contact_phone')
                : $existing?->contact_phone,
            'amount' => $request->input('amount', $existing?->amount),
            'date' => $request->input('date')
                ?? optional($existing?->date)->toDateString()
                ?? now($timezone)->toDateString(),
            'due_date' => $request->has('due_date')
                ? $request->input('due_date')
                : optional($existing?->due_date)->toDateString(),
            'status' => in_array($status, ['outstanding', 'overdue', 'paid'], true)
                ? $status
                : 'outstanding',
            'notes' => $request->has('notes')
                ? $request->input('notes')
                : $existing?->notes,
            'reminder_enabled' => $request->has('reminder_enabled')
                ? $request->boolean('reminder_enabled')
                : (bool) ($existing?->reminder_enabled ?? false),
            'reminder_channel' => $request->has('reminder_channel')
                ? $request->input('reminder_channel')
                : $existing?->reminder_channel,
            'reminder_frequency' => $request->has('reminder_frequency')
                ? $request->input('reminder_frequency')
                : $existing?->reminder_frequency,
            'next_reminder_at' => $request->has('next_reminder_at')
                ? $request->input('next_reminder_at')
                : optional($existing?->next_reminder_at)?->toIso8601String(),
        ];

        $validated = Validator::make($data, [
            'type' => ['required', 'in:borrowed,lent'],
            'person_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:outstanding,overdue,paid'],
            'notes' => ['nullable', 'string'],
            'reminder_enabled' => ['boolean'],
            'reminder_channel' => ['nullable', 'in:email,sms,both'],
            'reminder_frequency' => ['nullable', 'in:once,daily,every_3_days,weekly,fortnightly,monthly'],
            'next_reminder_at' => ['nullable', 'date'],
        ])->validate();

        if (($validated['status'] ?? '') === 'outstanding'
            && ! empty($validated['due_date'])
            && \Carbon\Carbon::parse($validated['due_date'])->isPast()) {
            $validated['status'] = 'overdue';
        }

        return $validated;
    }

    private function syncOverdue(Request $request): void
    {
        Debt::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['outstanding', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->update(['status' => 'overdue']);
    }

    private function owned(Request $request, int $id): Debt
    {
        return Debt::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }
}
