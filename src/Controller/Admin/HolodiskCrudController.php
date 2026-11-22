<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Holodisk;
use App\Enum\HolodiskMediaType;
use App\Service\HolodiskMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, Field, TextField, TextareaField};
use Symfony\Component\Form\Extension\Core\Type\FileType;

final class HolodiskCrudController extends ScopedCrudController
{
    public function __construct(private readonly HolodiskMediaStorage $mediaStorage)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Holodisk::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('larp')->setFormTypeOption('disabled', $pageName === Crud::PAGE_EDIT);
        yield TextField::new('title');
        yield TextField::new('code', 'Scan code')->hideWhenCreating()
            ->setHelp('Printed under the QR code. Enter the text of an existing printed code to reuse it; spaces and hyphens are ignored.');
        yield BooleanField::new('regenerateCode', 'Generate a new code')->onlyWhenUpdating()->renderAsSwitch(false)
            ->setHelp('Printed copies of the current code stop working.');
        yield TextareaField::new('content')->setRequired(false)->setFormTypeOption('empty_data', '')
            ->setHelp('Text shown on the holodisk. Optional when an image or recording is attached.');
        // Keyed on the stored file name: EasyAdmin's text field casts the value to a string first.
        yield TextField::new('mediaFilename', 'Media')->hideOnForm()->renderAsHtml()
            ->formatValue(fn (?string $filename, Holodisk $holodisk): string => $this->mediaPreview($holodisk));
        yield Field::new('mediaFile', 'Image or MP3')->onlyOnForms()->setFormType(FileType::class)
            ->setFormTypeOption('required', false)
            ->setHelp('JPEG, PNG, WebP or GIF image, or MP3 audio, up to 15 MB. Images are shown with the green screen tint; the file itself is not changed. Uploading replaces the current media.');
        yield BooleanField::new('removeMedia', 'Remove current media')->onlyWhenUpdating()->renderAsSwitch(false);
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::new('printCode', 'QR code')->linkToRoute('admin_holodisk_code', static fn (Holodisk $holodisk): array => ['id' => $holodisk->getId()]))
            ->add(Crud::PAGE_EDIT, Action::new('printCode', 'QR code')->linkToRoute('admin_holodisk_code', static fn (Holodisk $holodisk): array => ['id' => $holodisk->getId()]));
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->mediaStorage->sync($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $entityInstance->applyCodeRegeneration();
        $this->mediaStorage->sync($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        parent::deleteEntity($entityManager, $entityInstance);
        $this->mediaStorage->remove($entityInstance);
    }

    private function mediaPreview(Holodisk $holodisk): string
    {
        if (!$holodisk->hasMedia()) {
            return '<span class="holodisk-media-none">None</span>';
        }

        $src = htmlspecialchars($this->generateUrl('app_holodisk_media', ['id' => $holodisk->getId()]), ENT_QUOTES);

        return $holodisk->getMediaType() === HolodiskMediaType::IMAGE
            ? '<figure class="holodisk-media holodisk-media--image holodisk-media--thumb"><img src="'.$src.'" alt="" loading="lazy"></figure>'
            : '<audio class="holodisk-media--audio" controls preload="none" src="'.$src.'"></audio>';
    }
}
