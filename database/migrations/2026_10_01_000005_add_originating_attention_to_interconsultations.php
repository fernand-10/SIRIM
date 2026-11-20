<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('interconsultations', function (Blueprint $table) {
            $table->foreignId('originating_attention_id')->nullable()->after('parent_interconsultation_id')->constrained('medical_attentions')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('interconsultations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('originating_attention_id');
        });
    }
};
