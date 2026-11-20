<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('health_facilities',function(Blueprint $t){ $t->id(); $t->string('name',180)->unique(); $t->string('facility_type',40); $t->string('address')->nullable(); $t->string('phone',40)->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
  Schema::create('specialties',function(Blueprint $t){ $t->id(); $t->string('name',120)->unique(); $t->string('description',500)->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
  Schema::create('patients',function(Blueprint $t){ $t->id(); $t->string('document_type',20)->default('DNI'); $t->string('document_number',30)->unique(); $t->string('first_name',100); $t->string('last_name',120); $t->date('birth_date')->nullable(); $t->string('sex',20)->nullable(); $t->string('phone',30)->nullable(); $t->string('address')->nullable(); $t->timestamps(); $t->index(['last_name','first_name']); });
  Schema::create('professionals',function(Blueprint $t){ $t->id(); $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete(); $t->foreignId('specialty_id')->nullable()->constrained('specialties')->nullOnDelete(); $t->foreignId('health_facility_id')->nullable()->constrained('health_facilities')->nullOnDelete(); $t->string('license_number',80)->nullable(); $t->string('phone',40)->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
 }
 public function down(): void { Schema::dropIfExists('professionals'); Schema::dropIfExists('patients'); Schema::dropIfExists('specialties'); Schema::dropIfExists('health_facilities'); }
};
