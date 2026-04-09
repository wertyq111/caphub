<?php

use App\Clients\Ai\OpenClaw\OpenClawClient;
use App\Clients\Ai\OpenClaw\OpenClawTranslationGateway;
use App\Services\Translation\GlossaryHitPersister;
use App\Services\Translation\HtmlTextNodeTranslator;
use App\Services\Translation\TranslationModeResolver;
use App\Services\Translation\TranslationResultPersister;
use App\Services\Translation\TranslationService;
use Tests\TestCase;

uses(TestCase::class);

it('uses a sync cache lock window that outlasts the upstream timeout', function () {
    config()->set('services.openclaw.timeout', 30);

    $service = new class(
        Mockery::mock(OpenClawClient::class),
        Mockery::mock(OpenClawTranslationGateway::class),
        Mockery::mock(TranslationModeResolver::class),
        Mockery::mock(TranslationResultPersister::class),
        Mockery::mock(GlossaryHitPersister::class),
        Mockery::mock(HtmlTextNodeTranslator::class),
    ) extends TranslationService
    {
        public function syncCacheLockSecondsForTest(): int
        {
            return $this->syncCacheLockSeconds();
        }

        public function syncCacheLockWaitSecondsForTest(): int
        {
            return $this->syncCacheLockWaitSeconds();
        }
    };

    $lockSeconds = $service->syncCacheLockSecondsForTest();
    $waitSeconds = $service->syncCacheLockWaitSecondsForTest();

    expect($lockSeconds)->toBeGreaterThan(config('services.openclaw.timeout'));
    expect($waitSeconds)->toBeGreaterThanOrEqual(config('services.openclaw.timeout'));
    expect($waitSeconds)->toBeLessThan($lockSeconds);
});
