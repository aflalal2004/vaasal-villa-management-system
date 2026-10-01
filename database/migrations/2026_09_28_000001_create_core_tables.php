<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core platform: property, identity, RBAC, sessions, audit, notifications, settings, numbering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('address')->nullable();
            $t->string('city', 80)->nullable();
            $t->string('country', 80)->nullable();
            $t->string('tax_id', 60)->nullable();
            $t->string('timezone', 60)->default('Asia/Colombo');
            $t->char('currency', 3)->default('LKR');
            $t->decimal('tax_pct', 5, 2)->default(0);
            $t->decimal('service_charge_pct', 5, 2)->default(0);
            $t->time('check_in_time')->default('14:00');
            $t->time('check_out_time')->default('11:00');
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->json('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20)->unique();
            $t->string('name', 80);
            $t->string('description')->nullable();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 50)->unique();
            $t->string('name', 80);
            $t->string('description')->nullable();
            $t->string('home_route', 80)->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('module', 40)->index();
            $t->string('name', 120);
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $t->string('user_type', 20)->default('staff')->index(); // staff | operator
            $t->unsignedBigInteger('tour_operator_id')->nullable()->index(); // FK added in bookings migration
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone', 40)->nullable();
            $t->string('password');
            $t->string('status', 20)->default('active'); // active | inactive
            $t->string('theme', 10)->default('system'); // light | dark | system
            $t->string('avatar_path')->nullable();
            $t->unsignedSmallInteger('failed_attempts')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->timestamp('password_changed_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'role_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });

        Schema::create('login_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('email')->index();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->boolean('success')->default(false);
            $t->string('reason', 80)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('module', 40)->index();
            $t->string('action', 60);
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->string('description', 500)->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('permission', 80)->nullable()->index(); // broadcast to holders of permission
            $t->string('type', 60)->index();
            $t->string('level', 10)->default('info'); // info|success|warning|danger
            $t->string('title');
            $t->string('body', 1000)->nullable();
            $t->string('url')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('notification_reads', function (Blueprint $t) {
            $t->foreignId('app_notification_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at')->useCurrent();
            $t->primary(['app_notification_id', 'user_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 100)->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);
            $t->string('prefix', 20);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('next_number')->default(1);
            $t->unique(['type', 'year']);
        });
    }

    public function down(): void
    {
        foreach (['document_sequences', 'settings', 'notification_reads', 'app_notifications', 'audit_logs', 'login_logs',
            'failed_jobs', 'jobs', 'cache_locks', 'cache', 'sessions', 'password_reset_tokens', 'role_user', 'users',
            'permission_role', 'permissions', 'roles', 'departments', 'properties'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
