<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScrapingJobsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('scraping_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('reddit_link');
            $table->json('filters')->nullable();
            $table->string('google_drive_link')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'paused', 'stopped'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('content_type')->nullable();
            $table->string('folder_name')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('scraping_jobs');
    }
}
