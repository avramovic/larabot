<?php

namespace App\Enums;

enum SettingType: string
{
    case TYPE_STRING = 'string';
    case TYPE_INTEGER = 'int';
    case TYPE_FLOAT = 'float';
    case TYPE_BOOLEAN = 'bool';
    case TYPE_ARRAY = 'array';
}
