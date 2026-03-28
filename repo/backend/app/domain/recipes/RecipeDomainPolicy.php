<?php
declare(strict_types=1);

namespace app\domain\recipes;

final class RecipeDomainPolicy
{
    public function validateDraftToPublished(array $recipe): void
    {
        if (($recipe['prep_minutes'] ?? 0) <= 0) {
            throw new \DomainException('Recipe prep_minutes must be greater than zero before publishing');
        }
    }
}
