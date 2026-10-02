<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTestFlag;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contractor extends Model
{
    use Auditable, HasFactory, HasTestFlag, SoftDeletes, TracksAuthor;

    protected $guarded = ['id'];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function repairAttempts(): HasMany
    {
        return $this->hasMany(RepairAttempt::class);
    }
}
