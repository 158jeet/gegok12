<?php

namespace Database\Factories;

use App\Models\Teacherlink;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherlinkFactory extends Factory
{
    protected $model = Teacherlink::class;

    public function definition()
    {
        return [
            'status' => 1,
        ];
    }
}
