<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait HasPagination
{
    /**
     * Ambil nilai perPage dari request.
     * Default 15, maksimal 100 — supaya tidak ada yang minta perPage=999999
     */
    protected function getPerPage(Request $request, int $default = 10): int
    {
        $perPage = (int) $request->get('per_page', $default);
        return min(max($perPage, 1), 100);
    }

    /**
     * Format response pagination yang konsisten di semua endpoint
     */
    protected function paginateResponse($paginator, string $message = 'Data berhasil diambil'): array
    {
        return [
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ];
    }
}
