<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Validator\UniqueChapterNumberValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validateur des contraintes d'une entité, sans conteneur ni base de données : les règles d'unicité
 * (UniqueEntity, UniqueChapterNumber) sont neutralisées ici et testées en intégration.
 */
trait ValidatesWithoutDatabase
{
    private function validator(): ValidatorInterface
    {
        $withoutDatabase = new class extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
            }
        };

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                'doctrine.orm.validator.unique' => $withoutDatabase,
                UniqueChapterNumberValidator::class => $withoutDatabase,
            ]))
            ->getValidator();
    }
}
