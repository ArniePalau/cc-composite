<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Scheduler;

use ArniePalau\CcComposite\Service\FieldReportFeedSync;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask('1 hour', jitter: 120)]
final class FieldReportFeedTask
{
    public function __construct(private readonly FieldReportFeedSync $feedSync)
    {
    }

    public function __invoke(): void
    {
        $this->feedSync->sync();
    }
}
