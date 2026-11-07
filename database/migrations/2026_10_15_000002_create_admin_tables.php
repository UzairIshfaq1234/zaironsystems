<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('invited_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('invited_at');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        // Separate from password resets so a reset token can never be used with the longer invite lifetime.
        Schema::create('user_invitation_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('status', 20)->default('new')->after('details')->index();
            $table->text('notes')->nullable()->after('status');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('description', 255);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn(['status', 'notes']));
        Schema::dropIfExists('user_invitation_tokens');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'invited_at', 'last_login_at']));
    }
};
