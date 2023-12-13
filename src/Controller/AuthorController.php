<?php

namespace App\Controller;

use App\Entity\Author;
use JMS\Serializer\Serializer;
use App\Repository\AuthorRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'author', methods: ['GET'])]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getAllAuthors-" . $page . "-" . $limit;
        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
            $context = SerializationContext::create()->setGroups(['getAuthors']);
            $item->tag("authorsCache");
            $item->expiresAfter(60);
            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($authorList, 'json', $context);
        });

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors/{id}', name: 'detailAuthor', methods: ['GET'])]
    public function getDetailAuthor(SerializerInterface $serializer, Author $author): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un auteur')]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["authorsCache"]);
        $em->remove($author);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/api/authors', name: "createAuthor", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un auteur')]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $errors = $validator->validate($author);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $em->persist($author);
        $em->flush();
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/api/authors/{id}', name: "updateAuthor", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un auteur')]
    public function updateAuthor(Request $request,SerializerInterface $serializer,Author $currentAuthor,EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $currentAuthor->setFirstName($newAuthor->getFirstName());
        $currentAuthor->setLastName($newAuthor->getLastName());
        // On vérifie les erreurs
        $errors = $validator->validate($currentAuthor);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $em->persist($currentAuthor);
        $em->flush();
        $cache->invalidateTags(["authorsCache"]);
        return new JsonResponse(null,JsonResponse::HTTP_NO_CONTENT);
    }




}
