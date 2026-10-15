<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Note extends Model {
    use HasFactory;
    protected $fillable=['user_id','title','content','category','tags','color','is_pinned','is_favorite','is_archived'];
    protected $casts=['is_pinned'=>'boolean','is_favorite'=>'boolean','is_archived'=>'boolean'];
    public function user(){ return $this->belongsTo(User::class); }
}
