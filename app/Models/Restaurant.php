<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use SerializesLocalDates;

    protected $fillable = ['name', 'slug', 'color'];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
