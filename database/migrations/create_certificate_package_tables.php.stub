<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $templates = config('certificates.tables.templates', 'certificate_templates');
        $issued = config('certificates.tables.issued_certificates', 'issued_certificates');

        if (! Schema::hasTable($templates)) {
            Schema::create($templates, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('certificate_template');
                $table->json('settings')->nullable();
                $table->json('supported_modules')->nullable();
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->unique('name', 'certificate_templates_name_unique');
                $table->unique('slug', 'certificate_templates_slug_unique');
            });
        }

        if (! Schema::hasTable($issued)) {
            Schema::create($issued, function (Blueprint $table) use ($templates): void {
                $table->id();
                $table->foreignId('certificate_template_id')->constrained($templates)->restrictOnDelete();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('source_record_id');
                $table->string('certificate_number')->unique();
                $table->string('file_path');
                $table->json('data_snapshot')->nullable();
                $table->json('template_snapshot')->nullable();
                $table->timestamp('issued_at');
                $table->unsignedBigInteger('issued_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['source_type', 'source_record_id'], 'issued_certificates_source_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('certificates.tables.issued_certificates', 'issued_certificates'));
        Schema::dropIfExists(config('certificates.tables.templates', 'certificate_templates'));
    }
};
