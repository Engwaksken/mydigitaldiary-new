<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalGoal extends Model
{
    protected $fillable = ['user_id','module','title','description','start_date','target_date','target_value','current_value','progress_percent','status','priority','notes','reminder_at','is_archived'];
    public function user(){ return $this->belongsTo(User::class); }
    public function annualPlans(){ return $this->hasMany(Plan::class, 'personal_goal_id'); }
    public function dailyTasks(){ return $this->hasMany(DailyPlanItem::class, 'personal_goal_id'); }
    public function projectTasks(){ return $this->hasMany(ProjectTask::class, 'personal_goal_id'); }
    public function milestones(){ return $this->hasMany(GoalMilestone::class, 'personal_goal_id')->orderBy('sort_order')->orderBy('target_date'); }
    public function checkins(){ return $this->hasMany(GoalCheckin::class, 'personal_goal_id')->latest('week_start'); }
    public function reflections(){ return $this->hasMany(GoalReflection::class, 'personal_goal_id')->latest('reflected_on')->latest('id'); }

    protected $casts = ['start_date'=>'date','target_date'=>'date','target_value'=>'decimal:2','current_value'=>'decimal:2','progress_percent'=>'integer','reminder_at'=>'datetime','is_archived'=>'boolean'];

    /**
     * progress_percent is a NOT NULL column, but the field is optional on the
     * form/API (it's derived from target_value/current_value and recalculated
     * on save). Coerce null to 0 here so a blank field never violates the
     * NOT NULL constraint before the saved-event recalc kicks in.
     */
    protected function setProgressPercentAttribute($value): void
    {
        $this->attributes['progress_percent'] = $value === null ? 0 : (int) $value;
    }
}
