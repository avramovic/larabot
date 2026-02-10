<?php

namespace App\Enums;

enum FileType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case OTHER = 'other';
}
