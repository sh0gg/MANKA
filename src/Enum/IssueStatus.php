<?php

namespace App\Enum;

enum IssueStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::CLOSED => 'Pechada',
        };
    }
}
