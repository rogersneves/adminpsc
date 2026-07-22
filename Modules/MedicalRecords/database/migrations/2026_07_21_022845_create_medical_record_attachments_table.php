<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('medical_record_entry_id')->constrained('medical_record_entries')->cascadeOnDelete();
            $table->text('file_path_encrypted');
            $table->text('original_filename_encrypted');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['tenant_id', 'medical_record_entry_id'], 'mr_attachments_tenant_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_record_attachments');
    }
};
