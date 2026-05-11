<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileUploadValidator
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_FILE_SIZE = '5M';

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validateImage(UploadedFile $file): ConstraintViolationListInterface
    {
        $constraints = new Assert\File(
            maxSize: self::MAX_FILE_SIZE,
            mimeTypes: self::ALLOWED_MIME_TYPES,
            mimeTypesMessage: 'Invalid file type. Allowed types: JPEG, PNG, WebP',
            maxSizeMessage: 'File size exceeds 5MB limit',
        );

        return $this->validator->validate($file, $constraints);
    }

    /**
     * @param array<UploadedFile> $files
     *
     * @return array<string, string> Array of filename => error message
     */
    public function validateMultipleImages(array $files): array
    {
        $errors = [];

        foreach ($files as $index => $file) {
            $violations = $this->validateImage($file);
            if (count($violations) > 0) {
                $errors['page_'.($index + 1)] = (string) $violations->get(0)->getMessage();
            }
        }

        return $errors;
    }
}
