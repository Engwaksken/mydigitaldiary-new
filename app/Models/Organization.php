<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = ['name', 'owner_user_id', 'subscription_plan_id'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /** Active + invited seats - a deactivated seat's slot is considered freed. */
    public function seatsUsed(): int
    {
        return $this->members()->whereIn('status', ['invited', 'active'])->count();
    }

    public function seatLimit(): int
    {
        return $this->plan?->included_seats ?? 0;
    }

    public function hasSeatAvailable(): bool
    {
        return $this->seatsUsed() < $this->seatLimit();
    }

    public function remainingSeats(): int
    {
        return max(0, $this->seatLimit() - $this->seatsUsed());
    }
}
