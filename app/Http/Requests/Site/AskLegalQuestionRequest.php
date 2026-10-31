<?php

namespace App\Http\Requests\Site;

use App\Enums\LegalCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Public Tanya Hukum form. Spam protection: honeypot field, minimum fill
 * time and throttling (route middleware).
 */
class AskLegalQuestionRequest extends FormRequest
{
    public const MIN_SECONDS = 5;

    public const SESSION_KEY = 'legal_question_form_started';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_anonymous' => ['boolean'],
            'asker_name' => [Rule::requiredIf(! $this->boolean('is_anonymous')), 'nullable', 'string', 'max:100'],
            'asker_city' => ['nullable', 'string', 'max:100'],
            'asker_email' => ['nullable', 'email', 'max:255'],
            'category' => ['required', Rule::enum(LegalCategory::class)],
            'question' => ['required', 'string', 'min:30', 'max:5000'],
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
            'asker_name' => 'nama',
            'asker_city' => 'kota',
            'asker_email' => 'email',
            'category' => 'kategori',
            'question' => 'pertanyaan',
            'agree' => 'persetujuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asker_name.required' => 'Isi nama Anda atau pilih kirim sebagai anonim.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_anonymous' => $this->boolean('is_anonymous')]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $started = (int) $this->session()->get(self::SESSION_KEY, 0);

                if ($started === 0 || now()->getTimestamp() - $started < self::MIN_SECONDS) {
                    $validator->errors()->add('question', __('Formulir terkirim terlalu cepat. Silakan coba lagi.'));
                }
            },
        ];
    }
}
