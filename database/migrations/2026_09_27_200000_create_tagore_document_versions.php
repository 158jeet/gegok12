<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tagore_document_versions', function(Blueprint $table){
            $table->id();
            $table->foreignId('document_id')->constrained('tagore_document_records')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path',1000);
            $table->string('original_name',255);
            $table->string('mime_type',190)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->string('sha256',64);
            $table->timestamps();
            $table->unique(['document_id','version']);
        });
    }

    public function down(): void { Schema::dropIfExists('tagore_document_versions'); }
};
