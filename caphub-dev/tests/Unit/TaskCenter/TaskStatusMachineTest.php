<?php

namespace Tests\Unit\TaskCenter;

use App\Services\TaskCenter\TaskStatusMachine;
use App\Enums\TranslationJobStatus;
use PHPUnit\Framework\TestCase;

class TaskStatusMachineTest extends TestCase
{
    public function test_pending_can_transition_to_queued(): void
    {
        $machine = new TaskStatusMachine;

        $this->assertTrue(
            $machine->canTransition(TranslationJobStatus::Pending, TranslationJobStatus::Queued)
        );
    }
}
