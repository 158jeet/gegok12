<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Transport routes already exist in the fee/legacy transport layer.
        // Extend that canonical table instead of creating a conflicting duplicate.
        if (Schema::hasTable('tagore_transport_routes')) {
            Schema::table('tagore_transport_routes', function (Blueprint $table) {
                if (!Schema::hasColumn('tagore_transport_routes', 'name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('tagore_transport_routes', 'stops_json')) {
                    $table->text('stops_json')->nullable();
                }
                if (!Schema::hasColumn('tagore_transport_routes', 'estimated_minutes')) {
                    $table->integer('estimated_minutes')->default(0);
                }
                if (!Schema::hasColumn('tagore_transport_routes', 'fee')) {
                    $table->decimal('fee', 12, 2)->default(0);
                }
            });

            DB::table('tagore_transport_routes')
                ->whereNull('name')
                ->update(['name' => DB::raw('route_name')]);

            DB::table('tagore_transport_routes')
                ->whereNull('fee')
                ->update(['fee' => DB::raw('annual_amount')]);
        }

        if (!Schema::hasTable('tagore_transport_vehicles')) {
            Schema::create('tagore_transport_vehicles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->string('registration_no', 50);
                $table->string('vehicle_type', 50)->default('bus');
                $table->integer('capacity')->default(0);
                $table->string('gps_device_id', 100)->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->unique(['institution_id', 'registration_no']);
            });
        }

        if (!Schema::hasTable('tagore_transport_drivers')) {
            Schema::create('tagore_transport_drivers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->unsignedInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->string('license_no', 80)->nullable();
                $table->date('license_expiry')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->unique(['institution_id', 'user_id']);
            });
        }

        // The legacy table tagore_transport_assignments is a student-to-route
        // projection used by fee imports and parent authorization. Keep it intact.
        // Trip-level vehicle/driver assignments are represented by each trip.
        if (!Schema::hasTable('tagore_transport_trips')) {
            Schema::create('tagore_transport_trips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institution_id')->constrained('tagore_institutions')->cascadeOnDelete();
                $table->foreignId('route_id')->constrained('tagore_transport_routes')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('tagore_transport_vehicles')->cascadeOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('tagore_transport_drivers')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('status', 30)->default('planned');
                $table->timestamps();
                $table->index(['institution_id', 'status']);
            });
        }

        if (!Schema::hasTable('tagore_transport_gps_events')) {
            Schema::create('tagore_transport_gps_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->constrained('tagore_transport_trips')->cascadeOnDelete();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('speed_kmh', 8, 2)->nullable();
                $table->decimal('accuracy_m', 8, 2)->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();
                $table->index(['trip_id', 'recorded_at']);
            });
        }

        if (!Schema::hasTable('tagore_transport_boardings')) {
            Schema::create('tagore_transport_boardings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->constrained('tagore_transport_trips')->cascadeOnDelete();
                $table->unsignedInteger('student_id');
                $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
                $table->string('event_type', 20);
                $table->string('method', 30)->default('manual');
                $table->unsignedInteger('recorded_by')->nullable();
                $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
                $table->timestamp('recorded_at');
                $table->timestamps();
                $table->index(['trip_id', 'student_id', 'recorded_at'], 'tag_transport_boarding_scan_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagore_transport_boardings');
        Schema::dropIfExists('tagore_transport_gps_events');
        Schema::dropIfExists('tagore_transport_trips');
        Schema::dropIfExists('tagore_transport_drivers');
        Schema::dropIfExists('tagore_transport_vehicles');
    }
};
