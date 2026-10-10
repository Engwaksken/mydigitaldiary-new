<?php
namespace App\Services;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class DailyEngagementService {
 /**
  * Schema lookups memoized per process. dashboard() + review() used to run
  * ~60 information_schema queries per Today hub request (one per
  * hasTable/hasColumn call), which is slow on MySQL.
  *
  * @var array<string,bool>
  */
 private static array $schemaCache=[];
 private static function hasTable(string $table): bool {
  return self::$schemaCache[$table] ??= Schema::hasTable($table);
 }
 private static function hasColumn(string $table,string $column): bool {
  return self::$schemaCache[$table.'.'.$column] ??= Schema::hasColumn($table,$column);
 }
 public function timezoneFor(User $user): string {
  $tz=self::hasTable('engagement_preferences')?DB::table('engagement_preferences')->where('user_id',$user->id)->value('timezone'):null;
  return $tz ?: ($user->timezone ?? 'Africa/Kampala');
 }
 public function today(User $user): Carbon { return Carbon::now($this->timezoneFor($user))->startOfDay(); }
 public function dashboard(User $user): array {
  $today=$this->today($user); $focus=$this->topFocus($user,$today); $progress=$this->progress($user,$today);
  return ['date'=>$today->toDateString(),'timezone'=>$this->timezoneFor($user),'streak'=>$this->streak($user),
   'start_day'=>['completed'=>$this->hasCheckin($user,$today,'start_day'),'focus_count'=>count($focus)],
   'today_focus'=>$focus,'progress'=>$progress,'celebration'=>$this->celebration($user,$today),
   'close_day'=>['completed'=>$this->hasCheckin($user,$today,'close_day'),'available'=>true],
   'tomorrow'=>['date'=>$today->copy()->addDay()->toDateString(),'focus'=>$this->topFocus($user,$today->copy()->addDay())]];
 }
public function saveCheckin(User $user,string $type,array $data): array {
   abort_unless(in_array($type,['start_day','close_day'],true),422); $today=$this->today($user);
   DB::table('daily_checkins')->updateOrInsert(['user_id'=>$user->id,'checkin_date'=>$today->toDateString(),'type'=>$type],
    ['mood'=>$data['mood']??null,'reflection'=>$data['reflection']??null,'gratitude'=>$data['gratitude']??null,
     'tomorrow_focus'=>$data['tomorrow_focus']??null,'meta'=>isset($data['meta'])?json_encode($data['meta']):null,'updated_at'=>now(),'created_at'=>now()]);
   $this->syncWellbeingCheckin($user,$today,$data);
   return ['type'=>$type,'date'=>$today->toDateString(),'streak'=>$this->markMeaningfulAction($user,$type)];
  }
  private function syncWellbeingCheckin(User $user,Carbon $today,array $data): void {
   if(!self::hasTable('daily_wellbeing_logs')||!self::hasColumn('daily_wellbeing_logs','mood'))return;
   try {
    $log=app(DailyWellbeingSyncService::class)->sync($user,$today);
    $mood=isset($data['mood'])?(int)$data['mood']:null;
    $map=[1=>'low',2=>'okay',3=>'good',4=>'great',5=>'great'];
    $wellbeingMood=$mood!==null?($map[$mood]??null):null;
    if($wellbeingMood&&$log)$log->forceFill(['mood'=>$wellbeingMood])->save();
   } catch(\Throwable $exception){ report($exception); }
  }
 public function markMeaningfulAction(User $user,string $eventType,?string $sourceType=null,?int $sourceId=null,array $meta=[]): array {
  $today=$this->today($user);
  DB::table('engagement_events')->insert(['user_id'=>$user->id,'event_date'=>$today->toDateString(),'event_type'=>$eventType,
   'source_type'=>$sourceType,'source_id'=>$sourceId,'meta'=>$meta?json_encode($meta):null,'created_at'=>now(),'updated_at'=>now()]);
  return $this->updateStreak($user,$today);
 }
 public function weeklyReview(User $user): array { $d=$this->today($user); return $this->review($user,$d->copy()->startOfWeek(),$d->copy()->endOfWeek(),'week'); }
 public function monthlyReview(User $user): array { $d=$this->today($user); return $this->review($user,$d->copy()->startOfMonth(),$d->copy()->endOfMonth(),'month'); }
 public function shareCard(User $user,string $period): array {
  $r=$period==='week'?$this->weeklyReview($user):$this->monthlyReview($user);
  return ['period'=>$period,'title'=>$period==='week'?'My Week in Review':'My Month in Review','privacy_safe'=>true,'brand'=>'My Digital Diary',
   'metrics'=>[['label'=>'Tasks completed','value'=>(string)$r['tasks_completed']],['label'=>'Meaningful days','value'=>(string)$r['meaningful_days']],
   ['label'=>'Current streak','value'=>(string)($r['streak']['current'].' days')]]];
 }
 private function topFocus(User $user,Carbon $day): array {
  $items=[];

  if(self::hasTable('daily_plan_items') && self::hasTable('daily_plans')){
   $rows=DB::table('daily_plan_items as dpi')
    ->join('daily_plans as dp','dp.id','=','dpi.daily_plan_id')
    ->where('dp.user_id',$user->id)
    ->whereDate('dp.plan_date',$day->toDateString())
    ->orderByRaw("CASE WHEN dpi.priority='high' THEN 1 WHEN dpi.priority='medium' THEN 2 ELSE 3 END")
    ->orderBy('dpi.start_time')
    ->limit(5)
    ->get(['dpi.id','dpi.title','dpi.priority','dpi.start_time','dpi.is_completed']);

   foreach($rows as $row){
    if(($row->is_completed ?? false)) continue;
    $items[]=[
     'id'=>$row->id,
     'title'=>$row->title ?? 'Task',
     'source'=>'Daily Planner',
     'time'=>$row->start_time ?? null,
     'type'=>'daily_planner',
     'priority'=>$row->priority ?? null,
    ];
   }
  }

  if(self::hasTable('meetings')){
   $meetingDateColumn=self::hasColumn('meetings','meeting_date')
    ? 'meeting_date'
    : (self::hasColumn('meetings','date') ? 'date' : null);

   if($meetingDateColumn){
    $meetingRows=DB::table('meetings')
     ->where('user_id',$user->id)
     ->whereDate($meetingDateColumn,$day->toDateString())
     ->orderBy('start_time')
     ->limit(max(0,5-count($items)))
     ->get();

    foreach($meetingRows as $row){
     $items[]=[
      'id'=>$row->id,
      'title'=>$row->title ?? 'Meeting',
      'source'=>'Meeting',
      'time'=>$row->start_time ?? null,
      'type'=>'meeting',
     ];
    }
   }
  }

  return array_values($items);
 }
 private function progress(User $user,Carbon $day): array {
  $total=0;
  $done=0;
  $expenses=0.0;
  $income=0.0;
  $exercise=0;

  if(self::hasTable('daily_plan_items') && self::hasTable('daily_plans')){
   $q=DB::table('daily_plan_items as dpi')
    ->join('daily_plans as dp','dp.id','=','dpi.daily_plan_id')
    ->where('dp.user_id',$user->id)
    ->whereDate('dp.plan_date',$day->toDateString());

   $total=(clone $q)->count();

   if(self::hasColumn('daily_plan_items','status')){
    $done=(clone $q)->whereIn('dpi.status',['completed','done'])->count();
   } elseif(self::hasColumn('daily_plan_items','is_completed')){
    $done=(clone $q)->where('dpi.is_completed',true)->count();
   }
  }

  foreach([['expenses','expenses'],['incomes','income']] as [$table,$key]){
   if(self::hasTable($table) && self::hasColumn($table,'amount')){
    $dc=self::hasColumn($table,'date') ? 'date' : 'created_at';
    $$key=(float)DB::table($table)
     ->where('user_id',$user->id)
     ->whereDate($dc,$day->toDateString())
     ->sum('amount');
   }
  }

  if(self::hasTable('exercise_logs')){
   $dc=self::hasColumn('exercise_logs','date') ? 'date' : 'created_at';
   $exercise=DB::table('exercise_logs')
    ->where('user_id',$user->id)
    ->whereDate($dc,$day->toDateString())
    ->count();
  }

  $actions=self::hasTable('engagement_events')
   ? DB::table('engagement_events')
      ->where('user_id',$user->id)
      ->whereDate('event_date',$day->toDateString())
      ->count()
   : 0;

  return [
   'tasks_completed'=>$done,
   'tasks_total'=>$total,
   'completion_percent'=>$total ? round($done/$total*100) : 0,
   'expenses'=>$expenses,
   'income'=>$income,
   'exercise_sessions'=>$exercise,
   'meaningful_actions'=>$actions,
  ];
 }
 private function review(User $user,Carbon $start,Carbon $end,string $period): array {
  $done=0;
  $total=0;

  if(self::hasTable('daily_plan_items') && self::hasTable('daily_plans')){
   $q=DB::table('daily_plan_items as dpi')
    ->join('daily_plans as dp','dp.id','=','dpi.daily_plan_id')
    ->where('dp.user_id',$user->id)
    ->whereBetween('dp.plan_date',[
     $start->toDateString(),
     $end->toDateString(),
    ]);

   $total=(clone $q)->count();

   if(self::hasColumn('daily_plan_items','status')){
    $done=(clone $q)->whereIn('dpi.status',['completed','done'])->count();
   } elseif(self::hasColumn('daily_plan_items','is_completed')){
    $done=(clone $q)->where('dpi.is_completed',true)->count();
   }
  }

  $days=self::hasTable('engagement_events')
   ? DB::table('engagement_events')
      ->where('user_id',$user->id)
      ->whereBetween('event_date',[
       $start->toDateString(),
       $end->toDateString(),
      ])
      ->distinct('event_date')
      ->count('event_date')
   : 0;

  return [
   'period'=>$period,
   'from'=>$start->toDateString(),
   'to'=>$end->toDateString(),
   'tasks_completed'=>$done,
   'tasks_total'=>$total,
   'meaningful_days'=>$days,
   'streak'=>$this->streak($user),
  ];
 }
 private function hasCheckin(User $u,Carbon $d,string $type): bool {return self::hasTable('daily_checkins')&&DB::table('daily_checkins')->where('user_id',$u->id)->whereDate('checkin_date',$d->toDateString())->where('type',$type)->exists();}
 private function streak(User $u): array {$r=self::hasTable('engagement_streaks')?DB::table('engagement_streaks')->where('user_id',$u->id)->first():null;return ['current'=>(int)($r->current_streak??0),'best'=>(int)($r->best_streak??0),'last_day'=>$r->last_meaningful_day??null];}
 private function updateStreak(User $u,Carbon $today): array {$r=DB::table('engagement_streaks')->where('user_id',$u->id)->first();if(!$r){DB::table('engagement_streaks')->insert(['user_id'=>$u->id,'current_streak'=>1,'best_streak'=>1,'last_meaningful_day'=>$today->toDateString(),'created_at'=>now(),'updated_at'=>now()]);return $this->streak($u);} $last=$r->last_meaningful_day?Carbon::parse($r->last_meaningful_day,$this->timezoneFor($u))->startOfDay():null;if($last?->isSameDay($today))return $this->streak($u);$current=$last&&$last->copy()->addDay()->isSameDay($today)?$r->current_streak+1:1;$best=max((int)$r->best_streak,$current);DB::table('engagement_streaks')->where('user_id',$u->id)->update(['current_streak'=>$current,'best_streak'=>$best,'last_meaningful_day'=>$today->toDateString(),'updated_at'=>now()]);return $this->streak($u);}
 private function celebration(User $u,Carbon $today): ?array {$s=$this->streak($u);if(in_array($s['current'],[3,7,14,30,60,100,365],true))return ['type'=>'streak','title'=>$s['current'].'-Day Growth Streak','message'=>'You have kept showing up through meaningful actions.'];$p=$this->progress($u,$today);if($p['tasks_total']>=3&&$p['tasks_completed']===$p['tasks_total'])return ['type'=>'tasks','title'=>'Today’s plan completed','message'=>'You completed every planned task for today.'];return null;}
}
