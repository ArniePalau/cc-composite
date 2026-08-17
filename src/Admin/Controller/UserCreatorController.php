<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Admin\Controller;

use ArniePalau\CcComposite\Form\AdminNewUserType;
use ArniePalau\CcComposite\Form\DTO\AdminNewUser;
use Forumify\Core\Exception\UserAlreadyExistsException;
use Forumify\Core\Form\DTO\NewUser;
use Forumify\Core\Service\CreateUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('user-creator', name: 'user_creator')]
final class UserCreatorController extends AbstractController
{
    #[Route('', name: '_create', methods: ['GET', 'POST'])]
    public function create(Request $request, CreateUserService $createUserService): Response
    {
        $this->denyAccessUnlessGranted('forumify.admin.users.manage');
        $account = new AdminNewUser();
        $form = $this->createForm(AdminNewUserType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($account->isAdministrator()) {
                $this->denyAccessUnlessGranted('forumify.admin.users.manage_roles');
            }

            $newUser = new NewUser();
            $newUser->setUsername($account->getUsername());
            $newUser->setEmail($account->getEmail());
            $newUser->setPassword($account->getPassword());

            try {
                $user = $account->isAdministrator()
                    ? $createUserService->createAdmin($newUser)
                    : $createUserService->createUser($newUser, false);
                $this->addFlash('success', sprintf('User "%s" created%s.', $user->getUsername(), $account->isAdministrator() ? ' with administrator privileges' : ''));

                return $this->redirectToRoute('cc_composite_admin_user_creator_create');
            } catch (UserAlreadyExistsException) {
                $form->addError(new FormError('A user with that username or email already exists.'));
            }
        }

        return $this->render('@CcCompositePlugin/admin/users/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
