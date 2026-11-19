<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PayCategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TemplateItem>
 */
class TemplateItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paycategoryId = PayCategory::pluck('id')->random();

        return [
            'paycategory_id' => $paycategoryId,
            'category_value' => $paycategoryId == 4
                ? $this->faker->randomFloat(2, 100, 500)
                : 0,
        ];
    }
}
