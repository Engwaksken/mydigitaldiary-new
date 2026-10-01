<div>
    <label class="text-sm font-medium text-slate-700">Linked Goal</label>
    <select name="personal_goal_id" class="pm-input mt-2">
        <option value="">Choose a goal (optional)</option>
        @foreach(($goalOptions ?? collect()) as $goalId => $goalTitle)
            <option value="{{ $goalId }}">{{ $goalTitle }}</option>
        @endforeach
    </select>
    <p class="text-xs text-slate-500 mt-1">Link this plan to the outcome it is helping you achieve.</p>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Plan / Goal</label>
    <input name="title" required class="pm-input mt-2" placeholder="e.g. Complete professional certification">
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <div>
        <label class="text-sm font-medium text-slate-700">Year</label>
        <input type="number" name="plan_year" value="{{ $year }}" min="2000" max="2100" required class="pm-input mt-2">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Plan period</label>
        <select name="period" class="pm-input mt-2 annual-period-select" required>
            <option value="annually">Annual</option>
            <option value="monthly">Monthly</option>
        </select>
    </div>
    <div class="annual-month-field hidden">
        <label class="text-sm font-medium text-slate-700">Month</label>
        <select name="plan_month" class="pm-input mt-2">
            @foreach(range(1, 12) as $m)
                <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Progress %</label>
        <input type="number" name="progress_percent" value="0" min="0" max="100" required class="pm-input mt-2">
    </div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="text-sm font-medium text-slate-700">Target date</label>
        <input type="date" name="target_date" class="pm-input mt-2">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">Reminder date & time</label>
        <input type="datetime-local" name="reminder_at" class="pm-input mt-2">
        <p class="text-xs text-slate-500 mt-1">Optional. My Digital Diary will create a one-time reminder for this plan.</p>
    </div>
</div>
<div>
    <label class="text-sm font-medium text-slate-700">Description</label>
    <textarea name="description" rows="4" class="pm-input mt-2" placeholder="What do you want to achieve and how will you measure it?"></textarea>
</div>
