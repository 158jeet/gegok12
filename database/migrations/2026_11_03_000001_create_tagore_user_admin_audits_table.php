<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('tagore_user_admin_audits', function(Blueprint $table){$table->bigIncrements('id');$table->unsignedBigInteger('actor_id');$table->unsignedBigInteger('target_user_id');$table->string('action',40);$table->json('metadata')->nullable();$table->timestamp('created_at')->useCurrent();$table->index(['target_user_id','created_at']);$table->index(['actor_id','created_at']);});}
 public function down(): void { Schema::dropIfExists('tagore_user_admin_audits'); }
};
