<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin Users page filters as one reusable query, so the list and the
 * "select all users matching these filters" bulk actions always agree.
 */
class AdminUserFilter
{
    public const APPRECIATION_SENT = 'sent_this_week';
    public const APPRECIATION_NOT_SENT = 'not_sent_this_week';

    public const SUBSCRIPTION_STATUSES = ['active', 'trial', 'inactive', 'expired', 'suspended', 'cancelled'];

    public const KEYS = ['search', 'subscription_status', 'verification', 'appreciation'];

    public function __construct(
        public readonly string $search = '',
        public readonly string $subscriptionStatus = '',
        public readonly string $verification = '',
        public readonly string $appreciation = '',
    ) {
    }

    /** @param  array<string, mixed>  $filters  search, subscription_status, verification, appreciation */
    public static function fromArray(array $filters): self
    {
        $value = fn (string $key) => is_scalar($filters[$key] ?? null) ? trim((string) $filters[$key]) : '';

        return new self(
            $value('search'),
            $value('subscription_status'),
            in_array($value('verification'), ['verified', 'unverified'], true) ? $value('verification') : '',
            in_array($value('appreciation'), [self::APPRECIATION_SENT, self::APPRECIATION_NOT_SENT], true) ? $value('appreciation') : '',
        );
    }

    public function query(): Builder
    {
        $search = $this->search;
        $status = strtolower($this->subscriptionStatus);
        $weekKey = AppreciationMessageService::weekKey();

        return User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");

                    if (Schema::hasColumn('users', 'phone')) {
                        $sub->orWhere('phone', 'like', "%{$search}%");
                    }
                });
            })
            ->when(
                $status !== '' && Schema::hasColumn('users', 'subscription_status'),
                fn (Builder $query) => $query->whereIn(
                    // Legacy rows may still hold the old enum spellings.
                    DB::raw("LOWER(TRIM(COALESCE(subscription_status, '')))"),
                    match ($status) {
                        'trial', 'trialing' => ['trial', 'trialing'],
                        'cancelled', 'canceled' => ['cancelled', 'canceled'],
                        default => [$status],
                    }
                )
            )
            ->when($this->verification === 'verified', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($this->verification === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->when($this->appreciation !== '', function (Builder $query) use ($weekKey): void {
                $exists = fn ($sub) => $sub->selectRaw('1')
                    ->from('appreciation_email_logs')
                    ->whereColumn('appreciation_email_logs.user_id', 'users.id')
                    ->where('appreciation_email_logs.week_key', $weekKey);

                $this->appreciation === self::APPRECIATION_SENT
                    ? $query->whereExists($exists)
                    : $query->whereNotExists($exists);
            });
    }

    /** @return array<string, string> only the filters in use, for links and hidden inputs */
    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'subscription_status' => $this->subscriptionStatus,
            'verification' => $this->verification,
            'appreciation' => $this->appreciation,
        ], fn (string $value) => $value !== '');
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    /** @return list<string> e.g. ["Unverified email", "Active subscription"] */
    public function labels(): array
    {
        return array_values(array_filter([
            $this->search !== '' ? 'Search "'.$this->search.'"' : null,
            $this->verification === 'verified' ? 'Verified email' : null,
            $this->verification === 'unverified' ? 'Unverified email' : null,
            $this->subscriptionStatus !== '' ? ucfirst(strtolower($this->subscriptionStatus)).' subscription' : null,
            $this->appreciation === self::APPRECIATION_SENT ? 'Appreciation sent this week' : null,
            $this->appreciation === self::APPRECIATION_NOT_SENT ? 'Appreciation not sent this week' : null,
        ]));
    }

    public function description(): string
    {
        return $this->labels() === [] ? 'All users' : implode(', ', $this->labels());
    }
}
