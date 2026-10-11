<?php

namespace Tests\Unit;

use App\Services\Ai\AiTextFormatter;
use PHPUnit\Framework\TestCase;

class AiTextFormatterTest extends TestCase
{
    public function test_normalizes_long_dashes_recursively_in_generated_text(): void
    {
        $this->assertSame([
            'headline' => 'Plan - today',
            'items' => ['Sleep - 8 hours', ['note' => 'Meals - balanced']],
            'count' => 3,
        ], AiTextFormatter::normalize([
            'headline' => 'Plan — today',
            'items' => ['Sleep – 8 hours', ['note' => 'Meals — balanced']],
            'count' => 3,
        ]));
    }
}
