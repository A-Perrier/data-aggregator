<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Services\AggregatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;

class ArticleController extends AbstractController
{
    public function __construct(
        private readonly AggregatorService $aggregatorService,
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/articles/{article}/edit', name: 'edit_article_page', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Article $article): Response
    {
        $form = $this->createForm(ArticleType::class, $article);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'The article was successfully edited.');
        }

        return $this->render('article/edit.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/api/articles', name: 'get_articles', methods: ['GET'])]
    public function getArticles(Request $request): JsonResponse|Response
    {
        if ($request->isXmlHttpRequest()) {
            $format = $request->query->get('format', 'json');
            $allArticles = $this->aggregatorService->findAll();
            $responseData = $format === 'html' ? $this->renderView('templates/_parts/articles.html.twig', ['articles' => $allArticles]) : $allArticles;
            return new JsonResponse($responseData);
        }

        return new Response(null, Response::HTTP_FORBIDDEN);
    }

    #[Route('/api/articles/{article}', name: 'edit_article', methods: ['PUT'])]
    #[IsGranted("ROLE_ADMIN")]
    public function editArticle(Article $article): JsonResponse
    {
        // TODO
    }

    #[Route('/api/articles/{article}', name: 'delete_article', methods: ['DELETE'])]
    #[IsGranted("ROLE_ADMIN")]
    public function deleteArticle(Article $article): JsonResponse
    {
        try {
            $this->em->remove($article);
            $this->em->flush();
            $this->cache->deleteItem('articles');
            return new JsonResponse(['success' => true]);
        } catch (\Exception $exception) {
            return new JsonResponse(['success' => false], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    #[Route('/aggregate', name: 'aggregate')]
    #[IsGranted("ROLE_ADMIN")]
    public function aggregate(): JsonResponse
    {
        $result = $this->aggregatorService->aggregate();

        if (!is_null($result['data'])) {
            $htmlResponse = $this->renderView('_parts/articles.html.twig', ['articles' => $this->aggregatorService->findAll(true)]);
            $result['data'] = $htmlResponse;
        }

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
