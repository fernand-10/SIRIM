<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StatusHistory extends Model {
 public $timestamps = false;
 protected $fillable=['entity_type','entity_id','old_status','new_status','observations','changed_by','created_at'];
 protected function casts(): array { return ['created_at'=>'datetime']; }
 public function user(): BelongsTo { return $this->belongsTo(User::class,'changed_by'); }
}
