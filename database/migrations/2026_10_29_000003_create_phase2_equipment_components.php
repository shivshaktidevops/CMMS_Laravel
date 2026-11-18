<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->foreignId('parent_component_id')->nullable()->constrained('equipment_components')->nullOnDelete();
            $t->string('component_code', 80);
            $t->string('component_name', 150);
            $t->string('component_type', 80)->nullable();
            $t->string('serial_number', 100)->nullable();
            $t->string('manufacturer', 120)->nullable();
            $t->string('model_number', 120)->nullable();
            $t->date('installed_at')->nullable();
            $t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['equipment_id', 'component_code']);
            $t->index(['equipment_id', 'parent_component_id', 'active'], 'eq_component_tree_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_components');
    }
};
