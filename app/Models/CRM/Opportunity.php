<?php

namespace App\Models\CRM;

use App\Enums\OpportunityStage;
use App\Models\Accounting\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Opportunity extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'value',
        'probability',
        'stage',
        'expected_close_date',
        'assigned_to',
        'lost_reason',
        'won_at',
        'lost_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'stage' => OpportunityStage::class,
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function getWeightedValueAttribute(): float
    {
        return ($this->value ?? 0) * ($this->probability / 100);
    }

    public function getIsOpenAttribute(): bool
    {
        return ! in_array($this->stage, [OpportunityStage::Won, OpportunityStage::Lost]);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('stage', [OpportunityStage::Won, OpportunityStage::Lost]);
    }

    public function scopeWon($query)
    {
        return $query->where('stage', OpportunityStage::Won);
    }

    public function scopeLost($query)
    {
        return $query->where('stage', OpportunityStage::Lost);
    }

    public function scopeClosingThisMonth($query)
    {
        return $query->open()
            ->whereBetween('expected_close_date', [now()->startOfMonth(), now()->endOfMonth()]);
    }
}
