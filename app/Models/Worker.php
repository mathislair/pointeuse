<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    use SerializesLocalDates;

    protected $fillable = ['first_name', 'last_name', 'active'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
