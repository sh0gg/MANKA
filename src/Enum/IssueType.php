<?php

namespace App\Enum;

enum IssueType: string
{
    case PREVENTIVO = 'preventivo';
    case CORRECTIVO = 'correctivo';

    public function label(): string
    {
        return match ($this) {
            self::PREVENTIVO => 'Preventivo',
            self::CORRECTIVO => 'Correctivo',
        };
    }
}
