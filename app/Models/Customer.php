<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_contact
 * @property string|null $customer_address
 * @property string|null $company_name
 * @property string|null $ownership_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Customer extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_email',
        'customer_contact',
        'customer_address',
        'company_name',
        'ownership_type',
    ];

    /**
     * @param  array{
     *     customer_name?: mixed,
     *     customer_email?: mixed,
     *     customer_contact?: mixed,
     *     customer_address?: mixed,
     *     company_name?: mixed,
     *     ownership_type?: mixed
     * }  $data
     */
    public static function rememberFromIntake(array $data): self
    {
        $email = filled($data['customer_email'] ?? null) ? trim((string) $data['customer_email']) : null;
        $contact = filled($data['customer_contact'] ?? null) ? trim((string) $data['customer_contact']) : null;

        $existing = null;
        if ($email) {
            $existing = self::query()->where('customer_email', $email)->latest('id')->first();
        }
        if (! $existing && $contact) {
            $existing = self::query()->where('customer_contact', $contact)->latest('id')->first();
        }

        $payload = [
            'customer_name' => trim((string) ($data['customer_name'] ?? '')),
            'customer_email' => $email,
            'customer_contact' => $contact,
            'customer_address' => filled($data['customer_address'] ?? null) ? trim((string) $data['customer_address']) : null,
            'company_name' => filled($data['company_name'] ?? null) ? trim((string) $data['company_name']) : null,
            'ownership_type' => filled($data['ownership_type'] ?? null) ? trim((string) $data['ownership_type']) : null,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return self::query()->create($payload);
    }
}
