<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\AssignmentApproval;
use App\Models\Assignment;

class AssignmentApprovalFactory extends Factory
{
    protected $model = AssignmentApproval::class;

    public function definition()
    {
        return [
            'status'        => $this->faker->randomElement(['pending','approved','rejected']), 
        ];
    }
}
