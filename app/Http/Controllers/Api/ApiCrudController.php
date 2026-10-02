<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\ApiCrudIndexRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\OfflineConflictGuard;

/**
 * JSON equivalent of the web app's CrudController — every tracking module
 * gets a matching Api\{Module}Controller by setting just $model and
 * $rules (no $fields/$icon/$accent here; those exist only to drive the
 * web app's generic Blade form/table, which the Flutter app doesn't use —
 * it builds its own native UI from the JSON shape instead).
 *
 * All 20 web modules now have their API equivalent built this way.
 */
abstract class ApiCrudController extends Controller
{
    protected string $model;
    protected array $rules = [];

    /**
     * ?archived=1 shows ONLY archived items (for a dedicated "Archived"
     * view); omitted or any other value shows only non-archived ones —
     * archived items never mix into the normal list by default, which
     * is the whole point of archiving something.
     */
    public function index(ApiCrudIndexRequest $request): JsonResponse
    {
        $query = $this->filteredIndexQuery($request);
        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return response()->json($items);
    }

    protected function filteredIndexQuery(Request $request)
    {
        $showArchived = $request->boolean('archived');
        $model = new $this->model;
        $table = $model->getTable();
        $query = $this->model::where('user_id', $request->user()->id)
            ->where('is_archived', $showArchived);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $searchable = collect($model->getFillable())
                ->filter(fn ($column) => $column !== 'user_id' && Schema::hasColumn($table, $column))
                ->filter(fn ($column) => ! in_array($column, ['amount','target_amount','duration_minutes','calories','progress_percent'], true))
                ->values();
            if ($searchable->isNotEmpty()) {
                $query->where(function ($sub) use ($searchable, $search) {
                    foreach ($searchable as $column) {
                        $sub->orWhere($column, 'like', '%' . $search . '%');
                    }
                });
            }
        }

        if ($dateColumn = $this->resolveDateColumn($table)) {
            [$from, $to] = $this->resolvePeriodRange($request);
            if ($from && $to) {
                $query->whereBetween($dateColumn, [$from, $to]);
            }
        }

        return $query;
    }

    protected function resolveDateColumn(string $table): ?string
    {
        foreach (['received_at','spent_at','contributed_at','date','target_date','due_date','logged_at','performed_at','sleep_date','checked_at','practiced_at','created_at'] as $column) {
            if (Schema::hasColumn($table, $column)) return $column;
        }
        return null;
    }

    protected function resolvePeriodRange(Request $request): array
    {
        $period = (string) $request->query('period', '');
        $now = now();
        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => $this->customPeriodRange($request),
            default => [null, null],
        };
    }

    protected function customPeriodRange(Request $request): array
    {
        if (! $request->filled('from') || ! $request->filled('to')) return [null, null];
        $from = \Illuminate\Support\Carbon::parse($request->query('from'))->startOfDay();
        $to = \Illuminate\Support\Carbon::parse($request->query('to'))->endOfDay();
        if ($from->gt($to)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from' => 'The start date must be before or equal to the end date.']);
        }
        return [$from, $to];
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules);
        $data['user_id'] = $request->user()->id;

        $item = $this->model::create($data);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = OfflineConflictGuard::check($request, $item)) return $conflict;

        $data = $request->validate($this->rules);
        $item->update($data);

        return response()->json($item);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        if ($conflict = OfflineConflictGuard::check($request, $item)) return $conflict;
        $item->delete();

        return response()->json(['message' => 'Deleted.']);
    }


    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required','array','min:1'], 'ids.*' => ['integer']])['ids'];
        $deleted = $this->model::where('user_id', $request->user()->id)->whereIn('id', $ids)->delete();
        return response()->json(['message' => "{$deleted} item(s) deleted.", 'deleted' => $deleted]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        $item->update(['is_archived' => true]);

        return response()->json($item);
    }

    public function unarchive(Request $request, int $id): JsonResponse
    {
        $item = $this->model::where('user_id', $request->user()->id)->findOrFail($id);
        $item->update(['is_archived' => false]);

        return response()->json($item);
    }

    /**
     * A breakdown by 'status', matching the same stat-card shape the
     * web app's own list pages already show for status-driven modules
     * (see e.g. PlanController::index()'s Pending/In progress/
     * Completed cards) — only meaningful for modules that HAVE a
     * status column, hence the schema check rather than assuming one.
     */
    public function stats(Request $request): JsonResponse
    {
        $table = (new $this->model)->getTable();
        $userId = $request->user()->id;
        $total = $this->model::where('user_id', $userId)->where('is_archived', false)->count();

        if (! Schema::hasColumn($table, 'status')) {
            return response()->json(['total' => $total, 'by_status' => null]);
        }

        $byStatus = $this->model::where('user_id', $userId)
            ->where('is_archived', false)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(['total' => $total, 'by_status' => $byStatus]);
    }

    /**
     * A generic table PDF — every column in $rules except user_id,
     * humanized into a header (e.g. "target_date" -> "Target Date").
     * Not as polished as a hand-built per-module PDF would be, but
     * covers all 20 modules sharing this base class at once rather
     * than needing 20 separate templates.
     */
    public function downloadPdf(Request $request)
    {
        $userId = $request->user()->id;
        $items = $this->model::where('user_id', $userId)->where('is_archived', false)->orderByDesc('id')->get();

        $columns = array_values(array_filter(array_keys($this->rules), fn ($field) => $field !== 'user_id'));
        $headers = array_map(fn ($field) => Str::headline($field), $columns);

        $moduleLabel = Str::headline(class_basename($this->model));

        $pdf = Pdf::loadView('reports.generic-module', [
            'moduleLabel' => $moduleLabel,
            'columns' => $columns,
            'headers' => $headers,
            'items' => $items,
            'generatedFor' => $request->user()->name,
        ]);

        return $pdf->download(Str::slug($moduleLabel) . '-report.pdf');
    }
}
