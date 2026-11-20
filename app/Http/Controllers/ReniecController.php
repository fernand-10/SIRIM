<?php

namespace App\Http\Controllers;

use App\Services\ReniecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ReniecController extends Controller
{
    public function consultar(Request $request, ReniecService $reniec): JsonResponse
    {
        $data = $request->validate([
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $reniec->consultarDni($data['dni']),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo consultar el servicio de identidad. Verifique su configuración o continúe con el registro manual.',
            ], 503);
        }
    }
}
