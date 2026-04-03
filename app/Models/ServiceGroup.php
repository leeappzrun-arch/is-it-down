<?php

namespace App\Models;

use Database\Factories\ServiceGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class ServiceGroup extends Model
{
    /** @use HasFactory<ServiceGroupFactory> */
    use HasFactory;

    /**
     * Get the services assigned to the group.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_service_group');
    }

    /**
     * Get the recipients assigned directly to the group.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class, 'recipient_service_group');
    }

    /**
     * Get the recipient groups assigned to the group.
     */
    public function recipientGroups(): BelongsToMany
    {
        return $this->belongsToMany(RecipientGroup::class, 'recipient_group_service_group');
    }
}
