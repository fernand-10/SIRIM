<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class HealthFacility extends Model {
 protected $fillable=['name','facility_type','address','phone','is_active'];
 protected function casts(): array { return ['is_active'=>'boolean']; }
 public function outgoingReferrals(): HasMany { return $this->hasMany(Referral::class,'origin_facility_id'); }
 public function incomingReferrals(): HasMany { return $this->hasMany(Referral::class,'receiving_facility_id'); }
}
