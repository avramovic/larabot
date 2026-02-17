<?php

use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('prompt')->change();
            $table->string('title')->default('[unnamed task]')->after('schedule');
        });

        if (Task::count() > 0) {
            Task::firstOrCreate([
                'prompt' => 'Update *unnamed task* titles according to their prompts.',
                'title'  => 'Post migration task updates',
            ], [
                'schedule'    => '* * * * *',
                'repeat'      => 1,
                'enabled'     => true,
                'destination' => 'user',
            ]);
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->string('prompt')->change();
        });
    }
};
