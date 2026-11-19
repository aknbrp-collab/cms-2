<?php

namespace App\Models;

use App\Enums\WhatsAppEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp message to an applicant's guardian.
 *
 * @property string $id
 * @property string $admission_registration_id
 * @property string|null $user_id
 * @property WhatsAppEvent $event
 * @property string $channel "link" (opened in WhatsApp by an admin) or "gateway"
 * @property string $status "opened", "queued", "sent" or "failed"
 * @property string $phone
 * @property string $body
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AdmissionRegistration $registration
 * @property-read User|null $user
 */
#[Fillable(['admission_registration_id', 'user_id', 'event', 'channel', 'status', 'phone', 'body', 'error'])]
class AdmissionMessage extends Model
{
    use HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WhatsAppEvent::class,
        ];
    }

    /**
     * @return BelongsTo<AdmissionRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(AdmissionRegistration::class, 'admission_registration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
