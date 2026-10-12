<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{ChoiceField, EmailField, TextField};
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email');
        yield TextField::new('firstName');
        yield TextField::new('lastName');
        yield ChoiceField::new('roles')->setChoices(['Player' => 'ROLE_USER', 'Organizer' => 'ROLE_ORGANIZER', 'Superadministrator' => 'ROLE_SUPER_ADMIN'])->allowMultipleChoices();
        $password = TextField::new('plainPassword', 'New password')->setFormType(PasswordType::class)
            ->onlyOnForms()->setRequired($pageName === Crud::PAGE_NEW);
        if ($pageName === Crud::PAGE_NEW) {
            $password->setFormTypeOption('constraints', [new NotBlank()]);
        }
        yield $password;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashNewPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashNewPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashNewPassword(User $user): void
    {
        if ($user->getPlainPassword()) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPlainPassword()));
            $user->eraseCredentials();
        }
    }
}
