<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileUploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileUploadValidatorTest extends TestCase
{
    private FileUploadValidator $validator;
    private ValidatorInterface $mockValidator;

    protected function setUp(): void
    {
        $this->mockValidator = $this->createMock(ValidatorInterface::class);
        $this->validator = new FileUploadValidator($this->mockValidator);
    }

    public function testValidateImageReturnsViolations(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $violations = new ConstraintViolationList();
        $this->mockValidator->method('validate')->willReturn($violations);

        $result = $this->validator->validateImage($file);
        $this->assertSame($violations, $result);
    }

    public function testValidateMultipleImagesWithNoErrors(): void
    {
        $file1 = $this->createMock(UploadedFile::class);
        $file2 = $this->createMock(UploadedFile::class);
        $files = [$file1, $file2];

        $violations = new ConstraintViolationList();
        $this->mockValidator->method('validate')->willReturn($violations);

        $result = $this->validator->validateMultipleImages($files);
        $this->assertEmpty($result);
    }

    public function testValidateMultipleImagesWithErrors(): void
    {
        $file1 = $this->createMock(UploadedFile::class);
        $file2 = $this->createMock(UploadedFile::class);
        $files = [$file1, $file2];

        $violation1 = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violation1->method('getMessage')->willReturn('Invalid file type');

        $violations1 = new ConstraintViolationList([$violation1]);
        $violations2 = new ConstraintViolationList();

        $this->mockValidator->method('validate')
            ->willReturnOnConsecutiveCalls($violations1, $violations2);

        $result = $this->validator->validateMultipleImages($files);

        $this->assertArrayHasKey('page_1', $result);
        $this->assertSame('Invalid file type', $result['page_1']);
        $this->assertArrayNotHasKey('page_2', $result);
    }

}
