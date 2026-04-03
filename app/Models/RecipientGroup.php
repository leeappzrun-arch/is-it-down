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

    /**
     * Get the services assigned directly to the group.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'recipient_group_service');
    }

    /**
     * Get the service groups assigned directly to the group.
     */
    public function serviceGroups(): BelongsToMany
    {
        return $this->belongsToMany(ServiceGroup::class, 'recipient_group_service_group');
    }
}
