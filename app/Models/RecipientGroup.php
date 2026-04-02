<?php

namespace App\Models;

use Database\Factories\RecipientGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class RecipientGroup extends Model
{
    /** @use HasFactory<RecipientGroupFactory> */
    use HasFactory;

    /**
     * Get the recipients assigned to the group.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'recipient_group_recipient');
    }
}
