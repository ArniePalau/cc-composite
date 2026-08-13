<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Message;

use Forumify\Core\Messenger\AsyncMessageInterface;

final readonly class RegenerateUserMessage implements AsyncMessageInterface
{
    public function __construct(public int $userId)
    {
    }
}
