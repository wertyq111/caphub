<?php

use App\Services\Translation\GlossaryHitPersister;
use App\Services\Translation\HtmlTextNodeTranslator;
use App\Services\Translation\TranslationGatewayRouter;
use App\Services\Translation\TranslationModeResolver;
use App\Services\Translation\TranslationResultPersister;
use App\Services\Translation\TranslationService;
use Tests\TestCase;

uses(TestCase::class);

it('uses a sync cache lock window that outlasts the upstream timeout', function () {
    config()->set('services.openclaw.timeout', 30);

    $gateway = Mockery::mock(TranslationGatewayRouter::class);
    $gateway->shouldReceive('timeout')->andReturn(30);

    $service = new class(
        $gateway,
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
