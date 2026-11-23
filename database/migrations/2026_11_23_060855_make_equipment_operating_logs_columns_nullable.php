
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_operating_logs', function (Blueprint $table) {

            $table->unsignedBigInteger('equipment_id')
                ->nullable()
                ->change();

            $table->unsignedBigInteger('shift_log_id')
                ->nullable()
                ->change();

            $table->decimal('standard_rpm', 12, 2)
                ->nullable()
                ->change();

            $table->decimal('actual_rpm', 12, 2)
                ->nullable()
                ->change();

            $table->decimal('standard_load', 12, 2)
                ->nullable()
                ->change();

            $table->decimal('actual_load', 12, 2)
                ->nullable()
                ->change();

            $table->unsignedBigInteger('logged_by')
                ->nullable()
                ->change();

            $table->string('shift', 20)
                ->nullable()
                ->change();

            $table->text('remarks')
                ->nullable()
                ->change();

            $table->timestamp('logged_at')
                ->nullable()
                ->useCurrent()
                ->change();

            $table->timestamp('created_at')
                ->nullable()
                ->change();

            $table->timestamp('updated_at')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        // Rollback intentionally does not force NOT NULL,
        // because nullable columns may already contain NULL values.
    }
};
