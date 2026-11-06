<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa request API minta JSON.
 * Fungsi: Laravel lalu menganggap klien mengharapkan JSON, sehingga error tidak menjadi halaman HTML.
 * Parameter handle: $request permintaan masuk, $next middleware/controller berikutnya.
 * Kembalian: response dari lapisan berikutnya.
 */
class ForceJson
{
    /**
     * Menyetel header Accept sebelum request diteruskan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Halaman bawaan Laravel (/) dan health check (/up) tetap HTML.
        if (! $request->is('/') && ! $request->is('up')) {
            // Accept diubah agar exception handler dan response()->json konsisten.
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
