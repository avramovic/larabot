<?php

namespace App\Enums;

enum LLMProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GEMINI = 'gemini';
    case CUSTOM = 'custom';
}
