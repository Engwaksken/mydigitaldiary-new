<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Note extends Model {
    use HasFactory;
    protected $fillable=['user_id','title','content','category','tags','color','note_date','is_pinned','is_favorite','is_archived'];
    protected $casts=['note_date'=>'date','is_pinned'=>'boolean','is_favorite'=>'boolean','is_archived'=>'boolean'];

    protected static function booted(): void
    {
        // A diary note defaults to today's date unless the user picks one.
        static::creating(function (Note $note): void {
            if (empty($note->note_date)) {
                $note->note_date = now()->toDateString();
            }
        });
    }

    public function user(){ return $this->belongsTo(User::class); }
}
