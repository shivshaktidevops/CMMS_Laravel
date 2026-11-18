
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu_permissions', function (Blueprint $table) {

            $table->id();

            // Existing user role values
            $table->string('role', 50);

            // Unique menu identifier
            $table->string('menu_key', 100);

            // Menu display name
            $table->string('menu_name', 150);

            $table->timestamps();

            // One menu can be assigned only once per role
            $table->unique(
                ['role', 'menu_key'],
                'unique_role_menu'
            );

            $table->index('role');
            $table->index('menu_key');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_permissions');
    }
};
