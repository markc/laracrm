<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ActivityType: string implements HasColor, HasLabel, HasIcon
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Task = 'task';
    case Note = 'note';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'Phone Call',
            self::Email => 'Email',
            self::Meeting => 'Meeting',
            self::Task => 'Task',
            self::Note => 'Note',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Call => 'success',
            self::Email => 'info',
            self::Meeting => 'warning',
            self::Task => 'primary',
            self::Note => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Call => 'heroicon-o-phone',
            self::Email => 'heroicon-o-envelope',
            self::Meeting => 'heroicon-o-calendar',
            self::Task => 'heroicon-o-clipboard-document-check',
            self::Note => 'heroicon-o-document-text',
        };
    }
}
