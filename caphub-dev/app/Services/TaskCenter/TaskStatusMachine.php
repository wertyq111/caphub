<?php

namespace App\Services\TaskCenter;

use App\Enums\TranslationJobStatus;

class TaskStatusMachine
{
    /**
     * @var array<string, array<string>>
     */
    private array $allowedTransitions = [
        TranslationJobStatus::Pending->value => [
            TranslationJobStatus::Queued->value,
            TranslationJobStatus::Cancelled->value,
        ],
        TranslationJobStatus::Queued->value => [
            TranslationJobStatus::Processing->value,
            TranslationJobStatus::Cancelled->value,
        ],
        TranslationJobStatus::Processing->value => [
            TranslationJobStatus::Succeeded->value,
            TranslationJobStatus::Failed->value,
            TranslationJobStatus::Cancelled->value,
        ],
        TranslationJobStatus::Succeeded->value => [],
        TranslationJobStatus::Failed->value => [],
        TranslationJobStatus::Cancelled->value => [],
    ];

    public function canTransition(TranslationJobStatus $from, TranslationJobStatus $to): bool
    {
        return in_array($to->value, $this->allowedTransitions[$from->value] ?? [], true);
    }
}
