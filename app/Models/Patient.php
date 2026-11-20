<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Patient extends Model {
 protected $fillable=['document_type','document_number','first_name','last_name','birth_date','sex','phone','address'];
 protected function casts(): array { return ['birth_date'=>'date']; }
 public function referrals(): HasMany { return $this->hasMany(Referral::class); }
 public function interconsultations(): HasMany { return $this->hasMany(Interconsultation::class); }
 public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
 public function attentions(): HasMany { return $this->hasMany(MedicalAttention::class); }
 public function getFullNameAttribute(): string { return trim($this->first_name.' '.$this->last_name); }
}
