<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support;

use Illuminate\Support\HtmlString;

/**
 * What a bank register knows about the bank of an IBAN. Only the name is mandatory; every other field is null when
 * the register does not have it. The data comes from external registers, so it is escaped wherever it is rendered
 * ({@see self::toHtml()}).
 */
final readonly class BankDetails
{
    /** Status codes that have a translation (`bank.status.<code>`); any other status is shown as "Status: …". */
    public const array KNOWN_STATUSES = ['liquidation', 'closed', 'suspended'];

    /**
     * @param string|null $status null = the bank is active; otherwise a code from {@see self::KNOWN_STATUSES} or the register's own text
     */
    public function __construct(
        public string $name,
        public ?string $bic = null,
        public ?string $city = null,
        public ?string $address = null,
        public ?string $postcode = null,
        public ?string $status = null,
    ) {}

    /**
     * Builds the details from a data file entry: a plain string (the name only) or an array with the keys
     * name, bic, city, address, postcode, status. Anything else (or an entry without a name) gives null.
     */
    public static function fromEntry(mixed $entry): ?self
    {
        if (is_string($entry)) {
            return trim($entry) === '' ? null : new self(trim($entry));
        }

        if (!is_array($entry)) {
            return null;
        }

        $name = self::text($entry['name'] ?? null);

        if ($name === null) {
            return null;
        }

        return new self(
            $name,
            self::text($entry['bic'] ?? null),
            self::text($entry['city'] ?? null),
            self::text($entry['address'] ?? null),
            self::text($entry['postcode'] ?? null),
            self::text($entry['status'] ?? null),
        );
    }

    /**
     * The shape stored in a data file: the bare name when nothing else is known, otherwise an array without the empty fields.
     *
     * @return array<string, string>|string
     */
    public function toEntry(): string|array
    {
        $entry = array_filter([
            'name' => $this->name,
            'bic' => $this->bic,
            'city' => $this->city,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'status' => $this->status,
        ], static fn (?string $value): bool => $value !== null);

        return count($entry) === 1 ? $this->name : $entry;
    }

    public function isActive(): bool
    {
        return $this->status === null;
    }

    /**
     * "Name · BIC · City" as plain text (no status).
     */
    public function summary(): string
    {
        return implode(' · ', array_filter([$this->name, $this->bic, $this->city], static fn (?string $part): bool => $part !== null));
    }

    /**
     * The translated status ("In liquidation"), null for an active bank.
     */
    public function statusLabel(): ?string
    {
        if ($this->status === null) {
            return null;
        }

        return in_array($this->status, self::KNOWN_STATUSES, true)
            ? __('filament-iban::filament-iban.bank.status.'.$this->status)
            : __('filament-iban::filament-iban.bank.status.other', ['status' => $this->status]);
    }

    /**
     * Escaped HTML of the summary; a bank that is not active gets a danger-coloured status after it.
     */
    public function toHtml(): HtmlString
    {
        $html = e($this->summary());
        $status = $this->statusLabel();

        if ($status !== null) {
            $html .= ' <span class="fi-color fi-color-danger" style="color: light-dark(var(--danger-600), var(--danger-400)); font-weight: 500;">'.e($status).'</span>';
        }

        return new HtmlString($html);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
