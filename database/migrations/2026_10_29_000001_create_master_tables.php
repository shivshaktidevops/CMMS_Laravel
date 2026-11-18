<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('plants', function (Blueprint $t) { $t->increments('id'); $t->string('name',50)->unique(); $t->string('code',20)->unique(); $t->boolean('is_active')->default(true); $t->dateTime('created_at',6)->nullable(); });
  Schema::create('departments', function (Blueprint $t) { $t->increments('id'); $t->string('name',100); $t->unsignedInteger('plant_id')->nullable(); $t->boolean('is_active')->default(true); $t->dateTime('created_at',6)->nullable(); $t->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete(); });
  Schema::create('shifts', function (Blueprint $t) { $t->increments('id'); $t->string('name',20); $t->char('code',2); $t->unsignedInteger('plant_id')->nullable(); $t->boolean('is_active')->default(true); $t->dateTime('created_at',6)->nullable(); $t->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete(); });
  Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('username',50)->unique(); $t->string('password',255); $t->string('full_name',100); $t->string('role',20); $t->string('department',50)->nullable(); $t->string('shift',10)->nullable(); $t->boolean('active')->default(true); $t->dateTime('created_at',6)->nullable(); $t->dateTime('updated_at',6)->nullable(); $t->string('plant',20)->default('100TPD'); $t->index(['role','active']); });
  Schema::create('stop_reasons', function (Blueprint $t) { $t->increments('id'); $t->string('code',20)->unique(); $t->string('description',100); $t->string('category',50)->nullable(); $t->boolean('active')->default(true); $t->dateTime('created_at',6)->nullable(); });
 }
 public function down(): void { Schema::dropIfExists('stop_reasons'); Schema::dropIfExists('users'); Schema::dropIfExists('shifts'); Schema::dropIfExists('departments'); Schema::dropIfExists('plants'); }
};
