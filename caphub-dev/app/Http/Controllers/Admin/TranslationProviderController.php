<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TranslationProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTranslationProviderRequest;
use App\Services\Translation\TranslationProviderSettings;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TranslationProviderController extends Controller
{
    public function __construct(
        protected TranslationProviderSettings $settings,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(UpdateTranslationProviderRequest $request): JsonResponse
    {
        $provider = TranslationProvider::from((string) $request->validated('provider'));

        try {
            $this->settings->setCurrent($provider);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'provider' => $this->settings->current()->value,
            'providers' => $this->settings->providers(),
        ];
    }
}
