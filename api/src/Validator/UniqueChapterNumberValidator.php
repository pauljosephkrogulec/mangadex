<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Chapter;
use App\Repository\ChapterRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueChapterNumberValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ChapterRepository $chapters,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueChapterNumber) {
            throw new UnexpectedTypeException($constraint, UniqueChapterNumber::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof Chapter) {
            throw new UnexpectedValueException($value, Chapter::class);
        }

        if (!$this->chapters->findDuplicate($value) instanceof Chapter) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ number }}', $value->getNumber())
            ->setParameter('{{ language }}', $value->getLanguage())
            ->atPath('number')
            ->addViolation();
    }
}
