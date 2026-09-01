<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fallback when public/storage symlink fails on shared hosting.
 * Serves files from storage/app/public for GET /media/public/{path}.
 * Prefer storage:link (or copy fallback via /_ops/storage-link) when possible.
 */
class PublicStorageController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            throw new NotFoundHttpException;
        }

        $base = realpath(storage_path('app/public'));
        if ($base === false) {
            throw new NotFoundHttpException;
        }

        $full = realpath($base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

        if ($full === false || ! str_starts_with($full, $base.DIRECTORY_SEPARATOR) || ! is_file($full)) {
            throw new NotFoundHttpException;
        }

        $mime = File::mimeType($full) ?: 'application/octet-stream';

        return response()->file($full, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
