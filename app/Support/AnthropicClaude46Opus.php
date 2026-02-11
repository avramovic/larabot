<?php

namespace App\Support;

use Soukicz\Llm\Client\Anthropic\Model\AnthropicModel;

class AnthropicClaude46Opus extends AnthropicModel {
    public const VERSION_4_6 = '4-6';

    public function __construct(
        private string $version
    ) {
    }

    public function getCode(): string {
        return 'claude-opus-' . $this->version;
    }

    public function getInputPricePerMillionTokens(): float {
        return 5.0;
    }

    public function getOutputPricePerMillionTokens(): float {
        return 25.0;
    }

    public function getCachedInputPricePerMillionTokens(): float {
        return 6.25;
    }

    public function getCachedOutputPricePerMillionTokens(): float {
        return 0.5;
    }
}