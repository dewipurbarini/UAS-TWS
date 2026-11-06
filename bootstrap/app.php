<?php

use App\Http\Middleware\ForceJson;
use App\Http\Middleware\LogApi;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\RoleAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Route tugas ada di routes/api.php.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // String kosong menghilangkan awalan /api, sehingga path-nya /login dan /pengumuman.
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nama pendek agar route mudah dibaca.
        $middleware->alias([
            'jwt.auth' => JwtAuth::class,
            'role.admin' => RoleAdmin::class,
            'log.api' => LogApi::class,
            'force.json' => ForceJson::class,
        ]);

        // LogApi paling luar (berjalan pertama, selesai terakhir). ForceJson tepat di dalamnya.
        $middleware->prepend([
            LogApi::class,
            ForceJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua request selain halaman depan dan /up dianggap ingin JSON.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return ! $request->is('/') && ! $request->is('up');
        });

        // Menimpa halaman error HTML agar API tidak membocorkan SQL atau stack trace.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('/') || $request->is('up')) {
                return null;
            }

            $status = 500;
            $pesan = 'Terjadi kesalahan pada server';

            if ($e instanceof NotFoundHttpException) {
                $status = 404;
                $pesan = 'Route tidak ditemukan';
            } elseif ($e instanceof MethodNotAllowedHttpException) {
                $status = 405;
                $pesan = 'Method tidak diizinkan';
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
                $pesan = 'Token tidak ada';
            } elseif ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                // Pesan bawaan hanya dipakai bila bukan error server. Error 500 tetap generik.
                if ($status < 500 && $e->getMessage() !== '') {
                    $pesan = $e->getMessage();
                }
                if ($status >= 500) {
                    $pesan = 'Terjadi kesalahan pada server';
                }
            }

            return response()->json([
                'sukses' => false,
                'pesan' => $pesan,
            ], $status);
        });
    })->create();
