<?php

declare(strict_types=1);

namespace Corpuscom\Blost\SymfonyBlog;

use Corpuscom\Blost\SymfonyBlog\Entity\Post;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class BlogController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
    ) {}

    public function index(Request $request): Response
    {
        $page = max(1, min(1000000, $request->query->getInt('page', 1)));
        $posts = $this->em->createQueryBuilder()->select('p')->from(Post::class, 'p')
            ->where('p.publishedAt <= :now')->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE)
            ->orderBy('p.publishedAt', 'DESC')->addOrderBy('p.id', 'ASC')->setFirstResult(($page - 1) * 20)->setMaxResults(21)
            ->getQuery()->getResult();

        return new Response($this->twig->render('@BlostBlog/index.html.twig', [
            'posts' => array_slice($posts, 0, 20), 'page' => $page, 'has_next' => count($posts) > 20,
        ]));
    }

    public function show(string $id): Response
    {
        $post = $this->em->find(Post::class, $id);
        if ($post === null || $post->publishedAt > new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new NotFoundHttpException('Article not found.');
        }

        return new Response($this->twig->render('@BlostBlog/show.html.twig', ['post' => $post]));
    }
}
