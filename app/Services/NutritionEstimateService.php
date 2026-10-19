<?php

namespace App\Services;

use App\Services\Ai\ActiveAiClient;
use App\Services\Ai\PromptGuard;
use Illuminate\Support\Facades\Log;

class NutritionEstimateService
{
    public function __construct(private readonly ActiveAiClient $ai)
    {
    }

    public function estimateCalories(string $foodItems): ?array
    {
        $foodItems = trim($foodItems);

        if ($foodItems === '') {
            return null;
        }

        try {
            $result = $this->ai->json(
                <<<'SYSTEM'
You are the nutrition estimation helper inside My Digital Diary.
Estimate calories from the foods and portions the user explicitly entered.
Return JSON only.

Rules:
- This is a food-log estimate, not a diagnosis or medical prescription.
- Never invent an allergy, disease, medication, or portion the user did not state.
- If quantity is unclear, use a reasonable ordinary serving and say so in assumptions.
- Calories are approximate. Do not imply laboratory precision.
- Return:
  {
    "estimated_calories": integer,
    "confidence": "low|medium|high",
    "assumptions": "short plain-language explanation"
  }
SYSTEM,
                "Food items:\n" . $foodItems
            );

            $calories = (int) ($result['estimated_calories'] ?? 0);

            if ($calories <= 0 || $calories > 10000) {
                return null;
            }

            return [
                'calories' => $calories,
                'confidence' => in_array(($result['confidence'] ?? ''), ['low', 'medium', 'high'], true)
                    ? $result['confidence']
                    : 'medium',
                // Appended to the user's own notes, so cleaned like any saved output.
                'assumptions' => trim((string) PromptGuard::cleanOutput((string) ($result['assumptions'] ?? ''))),
            ];
        } catch (\Throwable $e) {
            Log::warning('Diet calorie AI estimate unavailable', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
