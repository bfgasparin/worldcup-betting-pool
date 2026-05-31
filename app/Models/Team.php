<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'is_placeholder'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_placeholder' => 'boolean',
        ];
    }

    /**
     * The public URL of the team's flag SVG, with a generic fallback for unknown qualifiers.
     *
     * @return Attribute<string, never>
     */
    protected function flagUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->code !== null
                ? '/flags/'.strtoupper($this->code).'.svg'
                : '/flags/_placeholder.svg',
        );
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withPivot('position')
            ->withTimestamps();
    }
}
