<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Reports\ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Reports\XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Live admin reports for any date range.
 *   GET /v1/admin/reports/{type}?limit=25            → summary + first rows (preview; up to 2,000 for print/PDF)
 *   GET /v1/admin/reports/{type}/download?format=csv  → full file: csv (default) or xlsx (Excel)
 */
class ReportController extends Controller
{
    private const PREVIEW_ROWS = 25;

    /** Upper bound for the printable (PDF) view, which renders rows in the browser. */
    private const MAX_PRINT_ROWS = 2000;

    public function show(Request $request, string $type): JsonResponse
    {
        $limit = (int) ($request->validate(['limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PRINT_ROWS]])['limit'] ?? self::PREVIEW_ROWS);
        $builder = $this->builder($request, $type);
        $report = $builder->definition();

        $rows = (clone $report['query'])->limit($limit)->get()->map($report['row'])->values();

        return response()->json([
            'type' => $type,
            'title' => $report['title'],
            'range' => $builder->range(),
            'columns' => $report['columns'],
            'rows' => $rows,
            'total_rows' => $this->count($report['query']),
            'summary' => ($report['summary'])(),
            'filename' => $builder->filename(),
        ]);
    }

    public function download(Request $request, string $type): StreamedResponse|BinaryFileResponse
    {
        $format = $request->validate(['format' => ['sometimes', Rule::in(['csv', 'xlsx'])]])['format'] ?? 'csv';
        $builder = $this->builder($request, $type);
        $report = $builder->definition();

        if ($format === 'xlsx') {
            $xlsx = new XlsxWriter($report['title']);
            $xlsx->header($report['columns']);
            foreach ((clone $report['query'])->cursor() as $record) {
                $xlsx->row(($report['row'])($record));
            }

            return response()
                ->download($xlsx->finish(), $builder->filename('xlsx'), [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-store',
                ])
                ->deleteFileAfterSend();
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ and names correctly
            fputcsv($out, $report['columns']);

            foreach ((clone $report['query'])->cursor() as $record) {
                fputcsv($out, array_map($this->safeCell(...), ($report['row'])($record)));
            }

            fclose($out);
        }, $builder->filename(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function builder(Request $request, string $type): ReportBuilder
    {
        abort_unless(in_array($type, ReportBuilder::TYPES, true), 404, 'Unknown report.');

        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['sometimes', 'nullable', Rule::in(Order::STATUSES)],
        ], ['to.after_or_equal' => 'The end date must be on or after the start date.']);

        return new ReportBuilder($type, $filters);
    }

    /** Row count for any query, including grouped ones. */
    private function count($query): int
    {
        $base = method_exists($query, 'toBase') ? $query->toBase() : $query;

        return (int) DB::query()->fromSub((clone $base)->reorder(), 'r')->count();
    }

    /** Spreadsheet apps run cells starting with = + - @ as formulas; prefix them so they stay text. */
    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)
            ? "'".$value
            : $value;
    }
}
