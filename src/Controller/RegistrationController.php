<?php

namespace App\Controller;

use App\Application\User\Register\RegisterUserCommand;
use App\Application\User\Register\RegisterUserHandler;
use App\Domain\User\UserAlreadyExistException;
use App\Entity\User;
use App\Form\RegistrationFormType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{

    public function __construct(
        private RegisterUserHandler $registrationUserHandler,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $registerUserCommand = new RegisterUserCommand($user->getEmail(), $plainPassword);
            try {
                $this->registrationUserHandler->handle($registerUserCommand);
                return $this->redirectToRoute('admin');
            } catch (UserAlreadyExistException) {
                // Cas métier attendu : l'utilisateur peut corriger lui-même, pas d'incident à loguer.
                $this->addFlash('error', 'Un compte existe déjà avec cet email.');
            } catch (\Exception $e) {
                $this->logger->error('Échec de l\'inscription', ['exception' => $e]);
                $this->addFlash('error', 'Une erreur est survenue, veuillez réessayer.');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
