<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Requests\StaffAttendanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TagoreStaffAttendanceFailureTest extends TestCase
{
    public function test_staff_attendance_failure_returns_safe_json_and_logs_the_exception(): void
    {
        $owner = User::query()->where('email', 'owner@tagore-demo.local')->firstOrFail();
        $this->actingAs($owner);

        $controller = Mockery::mock(StaffAttendanceController::class)->makePartial();
        $controller->shouldReceive('createStaffAttendance')
            ->once()
            ->andThrow(new RuntimeException('synthetic database detail'));

        Log::shouldReceive('error')
            ->once()
            ->with('Staff attendance could not be saved.', Mockery::on(function (array $context) use ($owner): bool {
                return $context['school_id'] === $owner->school_id
                    && $context['user_id'] === $owner->id
                    && $context['exception'] instanceof RuntimeException;
            }));

        $response = $controller->store(new StaffAttendanceRequest());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(
            'Unable to save staff attendance. Please check the details and try again.',
            $response->getData(true)['message']
        );
        $this->assertStringNotContainsString('synthetic database detail', json_encode($response->getData(true)));
    }
}
