<?php

namespace App\Cms;

/**
 * A form field of an admin resource (see App\Cms\Resource).
 */
final readonly class Field
{
    /**
     * @param  list<mixed>  $rules  Extra validation rules ("required" or "nullable" is added from $required).
     * @param  array<string, string>  $options  Value => label for "select" fields.
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public bool $required = false,
        public array $rules = [],
        public array $options = [],
        public ?string $help = null,
        public int $span = 1,
    ) {}

    public static function text(string $name, string $label, bool $required = false, ?string $help = null, int $max = 255): self
    {
        return new self($name, $label, 'text', $required, ['string', "max:{$max}"], help: $help);
    }

    public static function textarea(string $name, string $label, bool $required = false, ?string $help = null): self
    {
        return new self($name, $label, 'textarea', $required, ['string', 'max:5000'], help: $help, span: 2);
    }

    public static function richText(string $name, string $label): self
    {
        return new self($name, $label, 'richtext', false, ['string', 'max:100000'], span: 2);
    }

    public static function image(string $name, string $label): self
    {
        return new self($name, $label, 'image', false, ['exists:media_assets,id']);
    }

    public static function date(string $name, string $label, bool $required = false): self
    {
        return new self($name, $label, 'date', $required, ['date']);
    }

    public static function number(string $name, string $label): self
    {
        return new self($name, $label, 'number', false, ['integer', 'min:0', 'max:9999']);
    }

    public static function url(string $name, string $label): self
    {
        return new self($name, $label, 'url', false, ['url', 'max:2048']);
    }

    public static function email(string $name, string $label): self
    {
        return new self($name, $label, 'email', false, ['email', 'max:255']);
    }

    /**
     * @param  class-string<\BackedEnum>  $enum  An enum with a label() method.
     */
    public static function enum(string $name, string $label, string $enum): self
    {
        $options = [];

        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = method_exists($case, 'label') ? $case->label() : (string) $case->value;
        }

        return new self($name, $label, 'select', true, ['in:'.implode(',', array_keys($options))], $options);
    }

    /**
     * Full validation rules for this field.
     *
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        return [$this->required ? 'required' : 'nullable', ...$this->rules];
    }
}
