<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\Exceptions\Validation\ValidationException;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Validation\Validator;

trait ValidatesAttributes
{
    #[\Override]
    public function validate(): static
    {
        $result = Validator::check($this);
        if ($result->failed()) {
            throw new ValidationException($result->errors());
        }

        return $this;
    }

    #[\Override]
    public function isValid(): bool
    {
        return Validator::check($this)->passed();
    }

    /**
     * @return array<ValidationError>
     */
    #[\Override]
    public function errors(): array
    {
        return Validator::check($this)->errors();
    }
}
