<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * @param  class-string<JsonResource>  $resource
     * @return array{data: array<int, mixed>, meta: array{current_page: int, last_page: int, total: int}}
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(fn ($item) => (new $resource($item))->resolve(request()))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public static function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        return max(1, min($max, (int) $request->query('per_page', $default)));
    }

    /** The frontend sends ?search=, the mock reads ?q=. Both are accepted. */
    public static function search(Request $request): ?string
    {
        $term = trim((string) ($request->query('search', $request->query('q', ''))));

        return $term === '' ? null : $term;
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
