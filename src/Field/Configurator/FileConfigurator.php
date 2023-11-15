<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;
use function Symfony\Component\String\u;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class FileConfigurator implements FieldConfiguratorInterface
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return FileField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $configuredBasePath = $field->getCustomOption(FileField::OPTION_BASE_PATH);

        $formattedValue = \is_array($field->getValue())
            ? $this->getImagesPaths($field->getValue(), $configuredBasePath)
            : $this->getFilePath($field->getValue(), $configuredBasePath);
        $field->setFormattedValue($formattedValue);

        $field->setFormTypeOption('upload_filename', $field->getCustomOption(FileField::OPTION_UPLOADED_FILE_NAME_PATTERN));

        // this check is needed to avoid displaying broken links when file properties are optional
        if (null === $formattedValue || '' === $formattedValue || (\is_array($formattedValue) && 0 === \count($formattedValue)) || $formattedValue === rtrim($configuredBasePath ?? '', '/')) {
            $field->setTemplateName('label/empty');
        }

        if (!\in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_EDIT, Crud::PAGE_NEW], true)) {
            return;
        }

        $relativeUploadDir = $field->getCustomOption(FileField::OPTION_UPLOAD_DIR);
        if (null === $relativeUploadDir) {
            throw new \InvalidArgumentException(sprintf('The "%s" file field must define the directory where the files are uploaded using the setUploadDir() method.', $field->getProperty()));
        }
        $relativeUploadDir = u($relativeUploadDir)->trimStart(\DIRECTORY_SEPARATOR)->ensureEnd(\DIRECTORY_SEPARATOR)->toString();
        $isStreamWrapper = filter_var($relativeUploadDir, \FILTER_VALIDATE_URL);
        if ($isStreamWrapper) {
            $absoluteUploadDir = $relativeUploadDir;
        } else {
            $absoluteUploadDir = u($relativeUploadDir)->ensureStart($this->projectDir.\DIRECTORY_SEPARATOR)->toString();
        }
        $field->setFormTypeOption('upload_dir', $absoluteUploadDir);
    }

    private function getImagesPaths(?array $files, ?string $basePath): array
    {
        $filePaths = [];
        foreach ($files as $file) {
            $filePaths[] = $this->getFilePath($file, $basePath);
        }

        return $filePaths;
    }

    private function getFilePath(?string $filePath, ?string $basePath): ?string
    {
        // add the base path only to files that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null === $filePath || 0 !== preg_match('/^(http[s]?|\/\/)/i', $filePath)) {
            return $filePath;
        }

        // remove project path from filepath
        $filePath = str_replace($this->projectDir.\DIRECTORY_SEPARATOR.'public'.\DIRECTORY_SEPARATOR, '', $filePath);

        return isset($basePath)
            ? rtrim($basePath, '/').'/'.ltrim($filePath, '/')
            : '/'.ltrim($filePath, '/');
    }
}
