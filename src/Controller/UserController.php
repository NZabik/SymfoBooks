<?php

namespace App\Controller;

use App\Entity\User;
use OpenApi\Annotations as OA;
use App\Repository\UserRepository;
use App\Service\VersioningService;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Contracts\Cache\ItemInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }
    /**
    * Cette méthode permet de récupérer l'ensemble des utilisateurs.
    *
    * @OA\Response(
    *     response=200,
    *     description="Retourne la liste des utilisateurs",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=User::class,groups={"getUsers"}))
    *     )
    * )
    * @OA\Parameter(
    *     name="page",
    *     in="query",
    *     description="La page que l'on veut récupérer",
    *     @OA\Schema(type="int")
    * )
    *
    * @OA\Parameter(
    *     name="limit",
    *     in="query",
    *     description="Le nombre d'éléments que l'on veut récupérer",
    *     @OA\Schema(type="int")
    * )
    * @OA\Tag(name="Users")
    *
    * @param UserRepository $userRepository
    * @param SerializerInterface $serializerInterface
    * @param Request $request
    * @return JsonResponse
    */
    #[Route('/api/users', name: 'users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour voir les utilisateurs')]
    public function index(UserRepository $userRepository, SerializerInterface $serializerInterface, Request $request, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit',100);
        $idCache = "getUsers-" . $page . "-" . $limit;

        $jsonUsers = $cache->get($idCache, function (ItemInterface $item) use ($userRepository, $page, $limit, $serializerInterface, $versioningService) {
            $version = $versioningService->getVersion();
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $context->setVersion($version);
            $item->tag("usersCache");
            $item->expiresAfter(60);
            $users = $userRepository->findAllWithPagination($page, $limit);
            return $serializerInterface->serialize($users, 'json', $context);
        });

        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }
    /**
    * Cette méthode permet de rechercher un utilisateur par son ID.
    *
    * @OA\Response(
    *     response=200,
    *     description="Retourne un utilisateur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=User::class,groups={"getUsers"}))
    *     )
    * )
    * 
    * @OA\Tag(name="Users")
    *
    * @param User $user
    * @param SerializerInterface $serializerInterface
    * @return JsonResponse
    */
    #[Route('/api/users/{id}', name: 'userDetail', methods: ['GET'])]
        public function getUserDetail(User $user, SerializerInterface $serializerInterface, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $context->setVersion($version);
        $jsonUsers = $serializerInterface->serialize($user, 'json', $context);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, ['accept' => 'json'], true);
    }
    /**
    * Cette méthode permet de supprimer un utilisateur par son ID.
    *
    * @OA\Response(
    *     response=200,
    *     description="Supprime un utilisateur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=User::class,groups={"getUsers"}))
    *     )
    * )
    * 
    * @OA\Tag(name="Users")
    *
    * @param User $User
    * @return JsonResponse
    */
    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un utilisateur')]
    public function deleteUser(User $user, EntityManagerInterface $entityManagerInterface, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["usersCache"]);
        $entityManagerInterface->remove($user);
        $entityManagerInterface->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    /**
    * Cette méthode permet de créer un utilisateur.
    *
    * @OA\Response(
    *     response=200,
    *     description="Crée un utilisateur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=User::class,groups={"getUsers"}))
    *     )
    * )
    *
    *  @OA\RequestBody(
    *     required=true,
    *     @OA\JsonContent(
    *         example={
    *             "email": "email",
    *             "password": "password",
    *         },
    *           type="array",
    *           @OA\Items(ref=@Model(type=User::class,groups={"getUsers"})),
    *     )
    * )
    * @OA\Tag(name="Users")
    *
    * @param SerializerInterface $serializerInterface
    * @param EntityManagerInterface $entityManagerInterface
    * @param UrlGeneratorInterface $urlGenerator
    * @param Request $request
    * @return JsonResponse
    */
    #[Route('/api/register', name: 'addUser', methods: ['POST'])]
    public function addUser(EntityManagerInterface $entityManagerInterface, Request $request, SerializerInterface $serializerInterface, UrlGeneratorInterface $urlGeneratorInterface, ValidatorInterface $validator): JsonResponse
    {
        
        $user = $serializerInterface->deserialize($request->getContent(), User::class, 'json');
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $user->getPassword()));
        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse($serializerInterface->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [], true);
        }
        $entityManagerInterface->persist($user);
        $entityManagerInterface->flush();
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializerInterface->serialize($user, 'json', $context);
        $location = $urlGeneratorInterface->generate('userDetail', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['Location' => $location], true);

    }
    /**
    * Cette méthode permet de modifier un utilisateur.
    *
    * @OA\Response(
    *     response=200,
    *     description="Modifie un utilisateur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=User::class,groups={"getUsers"}))
    *     )
    * )
    *
    *  @OA\RequestBody(
    *     required=true,
    *     @OA\JsonContent(
    *         example={
    *             "email": "email",
    *             "password": "password",
    *         },
    *           type="array",
    *           @OA\Items(ref=@Model(type=User::class,groups={"getUsers"})),
    *     )
    * )
    * @OA\Tag(name="Users")
    *
    * @param SerializerInterface $serializerInterface
    * @param EntityManagerInterface $entityManagerInterface
    * @param UrlGeneratorInterface $urlGenerator
    * @param Request $request
    * @return JsonResponse
    */
    #[Route('/api/users/{id}', name: 'updateUser', methods: ['PUT'])]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour modifier un utilisateur')]
    public function updateUser(User $currentUser, EntityManagerInterface $entityManagerInterface, Request $request, SerializerInterface $serializerInterface, TagAwareCacheInterface $cache, ValidatorInterface $validator): JsonResponse
    {
        $user = $serializerInterface->deserialize($request->getContent(), User::class, 'json');
        $currentUser->setEmail($user->getEmail());
        $currentUser->setPassword($this->userPasswordHasher->hashPassword($user, $user->getPassword()));

        $errors = $validator->validate($currentUser);
        if ($errors->count() > 0) {
            return new JsonResponse($serializerInterface->serialize($errors,'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $entityManagerInterface->persist($currentUser);
        $entityManagerInterface->flush();

        $cache->invalidateTags(["usersCache"]);
        
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
