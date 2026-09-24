<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;

final readonly class RequestContractViolation
{
    /**
     * @param array<int, string> $filledVariants
     * @param array<int, array<string, mixed>> $violations
     */
    public function __construct(
        public string $contract,
        public ?string $discriminatorField,
        public mixed $discriminatorValue,
        public ?string $matchedVariant,
        public array $filledVariants,
        public array $violations,
    ) {
    }

    public function message(?LocalizationConfig $localization = null): string
    {
        return $this->definition()->render($localization);
    }

    public function definition(): Message
    {
        $first = $this->violations[0] ?? null;
        $value = is_array($first) ? ($first['message'] ?? null) : null;
        $details = is_string($value) || $value instanceof Message ? $value : new Message('pipeline.oneof_contract_violated');
        return new Message('pipeline.oneof_contract_violated.requestcontractviolation', ['value0' => $this->contract, 'details' => $details]);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(?LocalizationConfig $localization = null): array
    {
        return [
            'contract' => $this->contract,
            'discriminatorField' => $this->discriminatorField,
            'discriminatorValue' => $this->discriminatorValue,
            'matchedVariant' => $this->matchedVariant,
            'filledVariants' => $this->filledVariants,
            'violations' => array_map(static function (array $violation) use ($localization): array {
                if (($violation['message'] ?? null) instanceof Message) {
                    $violation['message'] = $violation['message']->render($localization);
                }
                return $violation;
            }, $this->violations),
        ];
    }
}
