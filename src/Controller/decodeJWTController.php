<?php

namespace App\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

class decodeJWTController extends AbstractController
{
    private $jwtEncoder;

    public function __construct(JWTEncoderInterface $jwtEncoder)
    {
        $this->jwtEncoder = $jwtEncoder;
    }

    #[Route('/api/decode', name: 'decodeJWT', methods: ['GET'])]
    public function decodeToken(Request $request)
    {
        $token = str_replace('bearer ', '', $request->headers->get('Authorization'));
        $data = $this->jwtEncoder->decode($token);

        if ($data === false) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse($data);
    }
}