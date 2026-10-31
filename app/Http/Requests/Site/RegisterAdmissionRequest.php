<?php

namespace App\Http\Requests\Site;

use App\Models\AdmissionPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public admission form. Spam protection: a hidden honeypot field and a
 * minimum time between showing and submitting the form (plus throttling).
 */
class RegisterAdmissionRequest extends FormRequest
{
    public const MIN_SECONDS = 5;

    /**
     * Only accept submissions while an open period takes online registrations.
     */
    public function authorize(): bool
    {
        return (bool) $this->period()?->acceptsRegistrations();
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }

    public function period(): ?AdmissionPeriod
    {
        return AdmissionPeriod::query()->open()->orderBy('closes_on')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $programs = $this->period()->programs ?? [];

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1990-01-01'],
            'nisn' => ['nullable', 'digits:10'],
            'previous_school' => ['required', 'string', 'max:255'],
            'program' => $programs === [] ? ['prohibited'] : ['required', Rule::in($programs)],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'agree' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap',
            'gender' => 'jenis kelamin',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'nisn' => 'NISN',
            'previous_school' => 'asal sekolah',
            'program' => 'program',
            'parent_name' => 'nama orang tua/wali',
            'parent_phone' => 'nomor HP orang tua/wali',
            'email' => 'email',
            'address' => 'alamat',
            'agree' => 'persetujuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_phone.regex' => 'Nomor HP harus nomor seluler Indonesia, misalnya 081234567890.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $started = (int) $this->session()->get('registration_form_started', 0);

                if ($started === 0 || now()->getTimestamp() - $started < self::MIN_SECONDS) {
                    $validator->errors()->add('full_name', __('Formulir terkirim terlalu cepat. Silakan coba lagi.'));
                }
            },
        ];
    }
}
