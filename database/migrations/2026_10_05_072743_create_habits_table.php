<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table សម្រាប់បង្កើតទម្លាប់
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('frequency')->default('daily'); // daily, weekdays, etc.
            $table->integer('streak_count')->default(0); // ចំនួនថ្ងៃដែលធ្វើបានជាប់ៗគ្នា 🔥
            $table->timestamps();
        });

        // Table សម្រាប់កត់ត្រារាល់ពេល Mark Complete ប្រចាំថ្ងៃ
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->onDelete('cascade');
            $table->date('completed_date');
            $table->timestamps();

            $table->unique(['habit_id', 'completed_date']); // ការពារកុំឱ្យ Mark ជាន់គ្នា 1 ថ្ងៃ 2 ដង
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
        Schema::dropIfExists('habits');
    }
};