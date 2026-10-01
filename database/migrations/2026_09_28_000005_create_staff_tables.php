<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff management, job roles, shifts & roster, time card / attendance, devices, leave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->string('title', 100);
            $t->foreignId('default_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $t->string('description', 300)->nullable();
            $t->timestamps();
            $t->unique(['department_id', 'title']);
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('employee_no', 20)->unique();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('job_role_id')->nullable()->constrained()->nullOnDelete();
            $t->string('first_name', 80);
            $t->string('last_name', 80);
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('gender', 10)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->text('national_id')->nullable(); // encrypted
            $t->string('address', 300)->nullable();
            $t->string('emergency_contact_name', 100)->nullable();
            $t->string('emergency_contact_phone', 40)->nullable();
            $t->string('employment_type', 20)->default('full_time'); // full_time|part_time|contract|intern
            $t->date('hire_date');
            $t->date('termination_date')->nullable();
            $t->string('status', 20)->default('active')->index(); // active|on_leave|terminated
            $t->string('photo_path')->nullable();
            $t->string('attendance_pin')->nullable(); // hashed
            $t->string('rfid_uid', 60)->nullable()->unique();
            $t->string('qr_token', 64)->nullable()->unique();
            $t->string('biometric_ref', 80)->nullable()->unique();
            $t->decimal('basic_salary', 12, 2)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('shifts', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60);
            $t->time('start_time');
            $t->time('end_time');
            $t->unsignedSmallInteger('grace_minutes')->default(10);
            $t->unsignedSmallInteger('break_minutes')->default(60);
            $t->string('color', 9)->default('#0E6B63');
            $t->timestamps();
        });

        Schema::create('roster_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $t->date('work_date');
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });

        Schema::create('attendance_devices', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('type', 20); // kiosk|rfid|qr|biometric
            $t->string('location', 100)->nullable();
            $t->string('api_token_hash', 64)->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->date('work_date');
            $t->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('clock_in')->nullable();
            $t->dateTime('clock_out')->nullable();
            $t->string('source_in', 20)->nullable(); // web|kiosk|rfid|qr|biometric|manual
            $t->string('source_out', 20)->nullable();
            $t->foreignId('device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $t->unsignedInteger('worked_minutes')->default(0);
            $t->unsignedInteger('late_minutes')->default(0);
            $t->unsignedInteger('early_leave_minutes')->default(0);
            $t->unsignedInteger('overtime_minutes')->default(0);
            $t->string('status', 20)->default('incomplete')->index(); // present|late|absent|on_leave|incomplete
            $t->string('ip_address', 45)->nullable();
            $t->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('correction_reason', 300)->nullable();
            $t->json('original_values')->nullable();
            $t->string('notes', 300)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'work_date']);
        });

        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name', 60);
            $t->unsignedSmallInteger('days_per_year')->default(0);
            $t->boolean('is_paid')->default(true);
            $t->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('days', 5, 1);
            $t->string('reason', 500)->nullable();
            $t->string('status', 20)->default('pending')->index(); // pending|approved|rejected|cancelled
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('remarks', 300)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['leave_requests', 'leave_types', 'attendance_records', 'attendance_devices', 'roster_entries', 'shifts', 'employees', 'job_roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
